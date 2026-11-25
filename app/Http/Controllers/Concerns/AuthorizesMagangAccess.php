<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Magang;
use App\Models\Mahasiswa;

/**
 * Aturan akses data turunan magang:
 * - Super Admin : akses seluruh prodi (role eksplisit di Backend).
 * - Admin : hanya mahasiswa pada prodi Admin (Admin tanpa prodi ditolak).
 * - Mahasiswa : hanya magang miliknya sendiri.
 * - Mitra : hanya magang mahasiswa pada mitra tersebut.
 * - Dosbing : ditolak karena akun dosbing belum terhubung ke data dosen pembimbing.
 */
trait AuthorizesMagangAccess
{
    /**
     * Apakah user adalah Super Admin (role eksplisit, bukan Admin tanpa prodi).
     */
    protected function isSuperAdmin($user): bool
    {
        return $user && $user->role === 'super_admin';
    }

    /**
     * Apakah user memiliki kewenangan administratif (Admin Prodi / Super Admin).
     */
    protected function isAdminRole($user): bool
    {
        return $user && in_array($user->role, ['admin', 'super_admin'], true);
    }

    /**
     * Prodi milik Admin yang sedang login (null untuk role lain).
     *
     * Super Admin sengaja mengembalikan null: akses lintas prodi ditangani
     * oleh cabang isSuperAdmin, bukan dengan menganggap prodi kosong.
     */
    protected function adminProdiId($user): ?int
    {
        if (!$user || $user->role !== 'admin') {
            return null;
        }

        $prodiId = optional($user->admin)->prodi_id;

        return $prodiId ? (int) $prodiId : null;
    }

    /**
     * Pastikan mahasiswa berada pada prodi Admin (Super Admin: semua prodi).
     */
    protected function mahasiswaInAdminProdi($user, $mahasiswaId): bool
    {
        if (!$mahasiswaId) {
            return false;
        }

        if ($this->isSuperAdmin($user)) {
            return Mahasiswa::where('mahasiswa_id', $mahasiswaId)->exists();
        }

        $prodiId = $this->adminProdiId($user);

        if (!$prodiId) {
            return false;
        }

        return Mahasiswa::where('mahasiswa_id', $mahasiswaId)
            ->where('prodi_id', $prodiId)
            ->exists();
    }

    /**
     * Cek apakah user berhak mengakses satu data magang.
     */
    protected function canAccessMagang($user, ?Magang $magang): bool
    {
        if (!$user || !$magang) {
            return false;
        }

        switch ($user->role) {
            case 'super_admin':
                // Akses eksplisit lintas prodi.
                return true;

            case 'admin':
                $prodiId = $this->adminProdiId($user);
                $ownerProdiId = (int) optional($magang->mahasiswa)->prodi_id;

                return $prodiId !== null && $ownerProdiId === $prodiId;

            case 'mahasiswa':
                $mahasiswaId = optional($user->mahasiswa)->mahasiswa_id;

                return $mahasiswaId
                    && (int) $magang->mahasiswa_id === (int) $mahasiswaId;

            case 'mitra':
                $mitraId = optional($user->mitra)->mitra_id;

                return $mitraId
                    && $magang->mitra_id
                    && (int) $magang->mitra_id === (int) $mitraId;

            case 'dosbing':
                // Belum ada relasi akun user -> dosen_pembimbing.
                return false;
        }

        return false;
    }

    /**
     * Hentikan request dengan 403 bila user tidak berhak atas data magang.
     */
    protected function authorizeMagangAccess($user, ?Magang $magang): void
    {
        if (!$this->canAccessMagang($user, $magang)) {
            abort(403, 'Anda tidak berhak mengakses data magang ini.');
        }
    }

    /**
     * Batasi query agar hanya memuat data yang boleh diakses user.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $relation  Relasi menuju Magang (boleh bertingkat, mis. logbook.magang).
     */
    protected function scopeByMagangAccess($query, $user, ?string $relation = 'magang')
    {
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        $apply = null;

        // Super Admin: tanpa pembatasan prodi/kepemilikan.
        if ($user->role === 'super_admin') {
            return $query;
        }

        if ($user->role === 'admin') {
            $prodiId = $this->adminProdiId($user);

            if (!$prodiId) {
                return $query->whereRaw('1 = 0');
            }

            $apply = function ($q) use ($prodiId) {
                $q->whereHas('mahasiswa', function ($mq) use ($prodiId) {
                    $mq->where('prodi_id', $prodiId);
                });
            };
        } elseif ($user->role === 'mahasiswa') {
            $mahasiswaId = optional($user->mahasiswa)->mahasiswa_id;

            if (!$mahasiswaId) {
                return $query->whereRaw('1 = 0');
            }

            $apply = function ($q) use ($mahasiswaId) {
                $q->where('mahasiswa_id', $mahasiswaId);
            };
        } elseif ($user->role === 'mitra') {
            $mitraId = optional($user->mitra)->mitra_id;

            if (!$mitraId) {
                return $query->whereRaw('1 = 0');
            }

            $apply = function ($q) use ($mitraId) {
                $q->where('mitra_id', $mitraId);
            };
        } else {
            // Role dosbing dan role lain tidak memiliki data turunan yang boleh diakses.
            return $query->whereRaw('1 = 0');
        }

        // Query yang sudah berupa model Magang tidak perlu whereHas.
        if ($relation === null) {
            $apply($query);

            return $query;
        }

        return $query->whereHas($relation, $apply);
    }

    /**
     * Jadwal presentasi tidak menyimpan relasi mahasiswa/magang di database,
     * sehingga hanya Admin dengan prodi (atau Super Admin) yang boleh mengubahnya.
     */
    protected function authorizeAdminWithProdi($user): void
    {
        if ($this->isSuperAdmin($user)) {
            return;
        }

        if (!$user || $user->role !== 'admin' || !$this->adminProdiId($user)) {
            abort(403, 'Hanya admin dengan program studi yang berhak mengubah jadwal presentasi.');
        }
    }
}
