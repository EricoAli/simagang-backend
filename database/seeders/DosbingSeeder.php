<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Dosbing;

class DosbingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $dosbingList = [
            ['nip' => '198001012005011001', 'nama' => 'Dr. Ir. Wahyono, M.Kom.'],
            ['nip' => '198202022006021002', 'nama' => 'Ir. Agus Harjoko, M.Sc., Ph.D.'],
            ['nip' => '198403032007031003', 'nama' => 'Muhammad Idham Fauzan, S.T., M.Eng.'],
            ['nip' => '198504042008042004', 'nama' => 'Faizah, S.Kom., M.Kom.'],
            ['nip' => '198605052009051005', 'nama' => 'Dr. Eng. Ganjar Alfian, S.T., M.Eng.'],
            ['nip' => '198706062010061006', 'nama' => 'Dr. Noor Akhmad Setiawan, S.T., M.T.'],
            ['nip' => '198807072011072007', 'nama' => 'Ir. Sri Suning Kusumawardani, S.T., M.T.'],
            ['nip' => '198908082012081008', 'nama' => 'Drs. Jazi Eko Istiyanto, M.Sc., Ph.D.'],
            ['nip' => '199009092013091009', 'nama' => 'Budi Raharjo, S.Kom., M.Cs.'],
            ['nip' => '199110102014102010', 'nama' => 'Dr. Mardhani Riasetiawan, S.E., M.T.'],
        ];

        foreach ($dosbingList as $dosen) {
            Dosbing::updateOrCreate(
                ['nip' => $dosen['nip']],
                ['nama' => $dosen['nama']]
            );
        }
    }
}
