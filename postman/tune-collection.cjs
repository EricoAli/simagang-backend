const fs = require('fs');
const path = require('path');

// Base collection structure
const col = {
  info: {
    _postman_id: "8c6b7891-9a3d-4c7b-b5d2-e3a1f94a2b10",
    name: "SIMAGANG - Super Admin & Admin Prodi",
    description: "Koleksi pengujian komprehensif API Super Admin dan Admin Program Studi (TRPL, TRIK, TRI, TRE) SIMAGANG DTEDI UGM. Mencakup autentikasi, manajemen Admin Prodi lintas prodi, dashboard agregat & isolasi, master data Mahasiswa, Dosen Pembimbing, Mitra, Magang, dan Jadwal Presentasi.",
    schema: "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  variable: [
    { key: "base_url", value: "http://127.0.0.1:8000/api", type: "string" },
    { key: "super_admin_token", value: "", type: "string" },
    { key: "token", value: "", type: "string" },
    { key: "token_trik", value: "", type: "string" },
    { key: "token_tri", value: "", type: "string" },
    { key: "token_tre", value: "", type: "string" },
    { key: "admin_id", value: "1", type: "string" },
    { key: "mahasiswa_id", value: "25", type: "string" },
    { key: "mahasiswa_trik_id", value: "27", type: "string" },
    { key: "mhs_trpl_nim", value: "24/111001/SV/11001", type: "string" },
    { key: "dosbing_id", value: "", type: "string" },
    { key: "mitra_id", value: "", type: "string" },
    { key: "new_mitra_name", value: "PT Solusi Teknologi", type: "string" },
    { key: "new_mitra_email", value: "hrd@solusitek.co.id", type: "string" },
    { key: "magang_id", value: "5", type: "string" },
    { key: "magang_trik_id", value: "6", type: "string" },
    { key: "jadwal_id", value: "", type: "string" }
  ],
  item: []
};

function makeTest(assertions) {
  return [
    {
      listen: "test",
      script: {
        type: "text/javascript",
        exec: assertions
      }
    }
  ];
}

function makePreAndTest(pre, test) {
  return [
    {
      listen: "prerequest",
      script: {
        type: "text/javascript",
        exec: pre
      }
    },
    {
      listen: "test",
      script: {
        type: "text/javascript",
        exec: test
      }
    }
  ];
}

const jsonHeader = { key: "Accept", value: "application/json", type: "text" };
const authHeader = (tokenVar) => ({
  type: "bearer",
  bearer: [{ key: "token", value: `{{${tokenVar}}}`, type: "string" }]
});

// 1. FOLDER 00: AUTHENTICATION
const authFolder = {
  name: "00 - Autentikasi (Super Admin & Admin 4 Prodi)",
  item: [
    {
      name: "Login Super Admin",
      event: makeTest([
        "let json = {}; try { json = pm.response.json(); } catch(e){}",
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));",
        "pm.test('Respons berformat JSON', () => pm.response.to.be.json);",
        "pm.test('Role adalah super_admin', () => pm.expect(json.role).to.eql('super_admin'));",
        "pm.test('Access token tersedia', () => pm.expect(json.access_token).to.be.a('string'));",
        "if (json.access_token) {",
        "    pm.collectionVariables.set('super_admin_token', json.access_token);",
        "    pm.environment.set('super_admin_token', json.access_token);",
        "}"
      ]),
      request: {
        method: "POST",
        header: [jsonHeader, { key: "Content-Type", value: "application/json" }],
        body: {
          mode: "raw",
          raw: JSON.stringify({ username: "superadmin", password: "SuperAdminPassword123!" }, null, 2)
        },
        url: { raw: "{{base_url}}/auth/login", host: ["{{base_url}}"], path: ["auth", "login"] }
      }
    },
    {
      name: "Login Admin TRPL",
      event: makeTest([
        "let json = {}; try { json = pm.response.json(); } catch(e){}",
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));",
        "pm.test('Respons berformat JSON', () => pm.response.to.be.json);",
        "pm.test('Role adalah admin', () => pm.expect(json.role).to.eql('admin'));",
        "pm.test('Access token TRPL tersedia', () => pm.expect(json.access_token).to.be.a('string'));",
        "if (json.access_token) {",
        "    pm.collectionVariables.set('token', json.access_token);",
        "    pm.environment.set('token', json.access_token);",
        "}"
      ]),
      request: {
        method: "POST",
        header: [jsonHeader, { key: "Content-Type", value: "application/json" }],
        body: {
          mode: "raw",
          raw: JSON.stringify({ username: "admin_trpl", password: "GANTI_DENGAN_PASSWORD_KUAT" }, null, 2)
        },
        url: { raw: "{{base_url}}/auth/login", host: ["{{base_url}}"], path: ["auth", "login"] }
      }
    },
    {
      name: "Login Admin TRIK",
      event: makeTest([
        "let json = {}; try { json = pm.response.json(); } catch(e){}",
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));",
        "pm.test('Respons berformat JSON', () => pm.response.to.be.json);",
        "pm.test('Role adalah admin', () => pm.expect(json.role).to.eql('admin'));",
        "pm.test('Access token TRIK tersedia', () => pm.expect(json.access_token).to.be.a('string'));",
        "if (json.access_token) {",
        "    pm.collectionVariables.set('token_trik', json.access_token);",
        "    pm.environment.set('token_trik', json.access_token);",
        "}"
      ]),
      request: {
        method: "POST",
        header: [jsonHeader, { key: "Content-Type", value: "application/json" }],
        body: {
          mode: "raw",
          raw: JSON.stringify({ username: "admin_trik", password: "GANTI_DENGAN_PASSWORD_KUAT" }, null, 2)
        },
        url: { raw: "{{base_url}}/auth/login", host: ["{{base_url}}"], path: ["auth", "login"] }
      }
    },
    {
      name: "Login Admin TRI",
      event: makeTest([
        "let json = {}; try { json = pm.response.json(); } catch(e){}",
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));",
        "pm.test('Respons berformat JSON', () => pm.response.to.be.json);",
        "pm.test('Role adalah admin', () => pm.expect(json.role).to.eql('admin'));",
        "if (json.access_token) {",
        "    pm.collectionVariables.set('token_tri', json.access_token);",
        "}"
      ]),
      request: {
        method: "POST",
        header: [jsonHeader, { key: "Content-Type", value: "application/json" }],
        body: {
          mode: "raw",
          raw: JSON.stringify({ username: "admin_tri", password: "AdminTRIpassword!2026" }, null, 2)
        },
        url: { raw: "{{base_url}}/auth/login", host: ["{{base_url}}"], path: ["auth", "login"] }
      }
    },
    {
      name: "Login Admin TRE",
      event: makeTest([
        "let json = {}; try { json = pm.response.json(); } catch(e){}",
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));",
        "pm.test('Respons berformat JSON', () => pm.response.to.be.json);",
        "pm.test('Role adalah admin', () => pm.expect(json.role).to.eql('admin'));",
        "if (json.access_token) {",
        "    pm.collectionVariables.set('token_tre', json.access_token);",
        "}"
      ]),
      request: {
        method: "POST",
        header: [jsonHeader, { key: "Content-Type", value: "application/json" }],
        body: {
          mode: "raw",
          raw: JSON.stringify({ username: "admin_tre", password: "AdminTREpassword!2026" }, null, 2)
        },
        url: { raw: "{{base_url}}/auth/login", host: ["{{base_url}}"], path: ["auth", "login"] }
      }
    }
  ]
};

// 2. FOLDER 01: SUPER ADMIN MANAGEMENT
const superAdminFolder = {
  name: "01 - Super Admin (Manajemen Admin Prodi & Dashboard)",
  item: [
    {
      name: "List Admin Prodi",
      event: makeTest([
        "let json = {}; try { json = pm.response.json(); } catch(e){}",
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));",
        "pm.test('Respons berformat JSON', () => pm.response.to.be.json);",
        "pm.test('Struktur pagination Laravel', () => {",
        "    pm.expect(json.data).to.be.an('array');",
        "    pm.expect(json.total).to.be.at.least(1);",
        "});",
        "if (json.data && json.data.length > 0) {",
        "    pm.collectionVariables.set('admin_id', json.data[0].admin_id);",
        "}"
      ]),
      request: {
        auth: authHeader("super_admin_token"),
        method: "GET",
        header: [jsonHeader],
        url: { raw: "{{base_url}}/super-admin/admin-prodi", host: ["{{base_url}}"], path: ["super-admin", "admin-prodi"] }
      }
    },
    {
      name: "Create Admin Prodi TRPL",
      event: makePreAndTest(
        [
          "const ts = Date.now();",
          "pm.collectionVariables.set('temp_admin_trpl_user', 'admin_trpl_' + ts);",
          "pm.collectionVariables.set('temp_admin_trpl_email', 'admin.trpl.' + ts + '@simagang.dev');"
        ],
        [
          "let json = {}; try { json = pm.response.json(); } catch(e){}",
          "pm.test('Status code 201 Created', () => pm.response.to.have.status(201));",
          "pm.test('Pesan sukses pembuatan akun', () => pm.expect(json.message).to.eql('Akun Admin Prodi berhasil dibuat'));",
          "pm.test('data.admin_id ada dan bertipe number', () => {",
          "    pm.expect(json.data.admin_id).to.be.a('number');",
          "    pm.collectionVariables.set('admin_id', json.data.admin_id);",
          "    pm.environment.set('admin_id', json.data.admin_id);",
          "});"
        ]
      ),
      request: {
        auth: authHeader("super_admin_token"),
        method: "POST",
        header: [jsonHeader, { key: "Content-Type", value: "application/json" }],
        body: {
          mode: "raw",
          raw: JSON.stringify({
            username: "{{temp_admin_trpl_user}}",
            email: "{{temp_admin_trpl_email}}",
            password: "PasswordSuperAdmin123!",
            nama: "Koordinator Magang TRPL Baru",
            prodi_id: 1
          }, null, 2)
        },
        url: { raw: "{{base_url}}/super-admin/admin-prodi", host: ["{{base_url}}"], path: ["super-admin", "admin-prodi"] }
      }
    },
    {
      name: "Create Admin Prodi TRIK",
      event: makePreAndTest(
        [
          "const ts = Date.now();",
          "pm.collectionVariables.set('temp_admin_trik_user', 'admin_trik_' + ts);",
          "pm.collectionVariables.set('temp_admin_trik_email', 'admin.trik.' + ts + '@simagang.dev');"
        ],
        [
          "let json = {}; try { json = pm.response.json(); } catch(e){}",
          "pm.test('Status code 201 Created', () => pm.response.to.have.status(201));",
          "pm.test('Pesan sukses pembuatan akun', () => pm.expect(json.message).to.eql('Akun Admin Prodi berhasil dibuat'));"
        ]
      ),
      request: {
        auth: authHeader("super_admin_token"),
        method: "POST",
        header: [jsonHeader, { key: "Content-Type", value: "application/json" }],
        body: {
          mode: "raw",
          raw: JSON.stringify({
            username: "{{temp_admin_trik_user}}",
            email: "{{temp_admin_trik_email}}",
            password: "PasswordSuperAdmin123!",
            nama: "Koordinator Magang TRIK Baru",
            prodi_id: 2
          }, null, 2)
        },
        url: { raw: "{{base_url}}/super-admin/admin-prodi", host: ["{{base_url}}"], path: ["super-admin", "admin-prodi"] }
      }
    },
    {
      name: "Detail Admin Prodi",
      event: makeTest([
        "let json = {}; try { json = pm.response.json(); } catch(e){}",
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));",
        "pm.test('admin_id cocok dengan parameter', () => pm.expect(json.admin_id).to.eql(Number(pm.variables.get('admin_id'))));",
        "pm.test('Relasi user dan prodi dimuat lengkap', () => {",
        "    pm.expect(json.user).to.be.an('object');",
        "    pm.expect(json.prodi).to.be.an('object');",
        "});"
      ]),
      request: {
        auth: authHeader("super_admin_token"),
        method: "GET",
        header: [jsonHeader],
        url: { raw: "{{base_url}}/super-admin/admin-prodi/{{admin_id}}", host: ["{{base_url}}"], path: ["super-admin", "admin-prodi", "{{admin_id}}"] }
      }
    },
    {
      name: "Update Admin Prodi",
      event: makeTest([
        "let json = {}; try { json = pm.response.json(); } catch(e){}",
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));",
        "pm.test('Pesan sukses pembaruan', () => pm.expect(json.message).to.eql('Akun Admin Prodi berhasil diperbarui'));",
        "pm.test('Nama admin terbarui', () => pm.expect(json.data.nama).to.eql('Koordinator Magang TRPL Pembaruan'));"
      ]),
      request: {
        auth: authHeader("super_admin_token"),
        method: "PUT",
        header: [jsonHeader, { key: "Content-Type", value: "application/json" }],
        body: {
          mode: "raw",
          raw: JSON.stringify({ nama: "Koordinator Magang TRPL Pembaruan" }, null, 2)
        },
        url: { raw: "{{base_url}}/super-admin/admin-prodi/{{admin_id}}", host: ["{{base_url}}"], path: ["super-admin", "admin-prodi", "{{admin_id}}"] }
      }
    },
    {
      name: "Toggle Status Admin Prodi (Aktif / Nonaktif)",
      event: makeTest([
        "let json = {}; try { json = pm.response.json(); } catch(e){}",
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));",
        "pm.test('Status berhasil dinonaktifkan', () => pm.expect(json.data.is_active).to.eql(false));",
        "pm.test('Pesan penonaktifan berhasil', () => pm.expect(json.message).to.eql('Akun Admin Prodi berhasil dinonaktifkan'));"
      ]),
      request: {
        auth: authHeader("super_admin_token"),
        method: "PATCH",
        header: [jsonHeader, { key: "Content-Type", value: "application/json" }],
        body: {
          mode: "raw",
          raw: JSON.stringify({ is_active: false }, null, 2)
        },
        url: { raw: "{{base_url}}/super-admin/admin-prodi/{{admin_id}}/status", host: ["{{base_url}}"], path: ["super-admin", "admin-prodi", "{{admin_id}}", "status"] }
      }
    },
    {
      name: "Dashboard Super Admin (Agregat 4 Prodi)",
      event: makeTest([
        "let json = {}; try { json = pm.response.json(); } catch(e){}",
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));",
        "pm.test('Mode adalah agregat', () => pm.expect(json.mode).to.eql('agregat'));",
        "pm.test('Statistik keseluruhan lengkap', () => {",
        "    pm.expect(json.statistik_keseluruhan).to.have.property('total_mahasiswa');",
        "    pm.expect(json.statistik_keseluruhan).to.have.property('total_admin_prodi');",
        "    pm.expect(json.statistik_keseluruhan).to.have.property('total_mitra');",
        "    pm.expect(json.statistik_keseluruhan).to.have.property('total_dosbing');",
        "});",
        "pm.test('Breakdown mencakup 4 program studi DTEDI', () => pm.expect(json.breakdown_per_prodi.length).to.eql(4));"
      ]),
      request: {
        auth: authHeader("super_admin_token"),
        method: "GET",
        header: [jsonHeader],
        url: { raw: "{{base_url}}/super-admin/dashboard", host: ["{{base_url}}"], path: ["super-admin", "dashboard"] }
      }
    },
    {
      name: "Dashboard Super Admin (Filter per Prodi)",
      event: makeTest([
        "let json = {}; try { json = pm.response.json(); } catch(e){}",
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));",
        "pm.test('Mode adalah filter', () => pm.expect(json.mode).to.eql('filter'));",
        "pm.test('Data prodi TRPL sesuai', () => pm.expect(json.prodi.kode_prodi).to.eql('TRPL'));",
        "pm.test('Statistik prodi TRPL tersedia', () => pm.expect(json.statistik).to.have.property('jumlah_mahasiswa'));"
      ]),
      request: {
        auth: authHeader("super_admin_token"),
        method: "GET",
        header: [jsonHeader],
        url: {
          raw: "{{base_url}}/super-admin/dashboard?prodi_id=1",
          host: ["{{base_url}}"],
          path: ["super-admin", "dashboard"],
          query: [{ key: "prodi_id", value: "1" }]
        }
      }
    }
  ]
};

// 3. FOLDER 02: ADMIN DASHBOARD
const adminDashboardFolder = {
  name: "02 - Admin (Dashboard Terisolasi Prodi)",
  item: [
    {
      name: "Dashboard Admin (Terisolasi TRPL)",
      event: makeTest([
        "let json = {}; try { json = pm.response.json(); } catch(e){}",
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));",
        "pm.test('Respons berformat JSON', () => pm.response.to.be.json);",
        "pm.test('Statistik Admin Prodi lengkap', () => {",
        "    pm.expect(json).to.have.property('jumlah_mahasiswa_aktif_magang');",
        "    pm.expect(json).to.have.property('jumlah_mahasiswa');",
        "    pm.expect(json).to.have.property('jumlah_mitra');",
        "    pm.expect(json).to.have.property('jumlah_dosbing');",
        "});"
      ]),
      request: {
        auth: authHeader("token"),
        method: "GET",
        header: [jsonHeader],
        url: { raw: "{{base_url}}/dashboard/admin", host: ["{{base_url}}"], path: ["dashboard", "admin"] }
      }
    }
  ]
};

// 4. FOLDER 03: MANAJEMEN MAHASISWA
const mahasiswaFolder = {
  name: "03 - Admin (Manajemen Mahasiswa Multi-Prodi)",
  item: [
    {
      name: "List Mahasiswa (Isolasi Prodi TRPL)",
      event: makeTest([
        "let json = {}; try { json = pm.response.json(); } catch(e){}",
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));",
        "pm.test('Data array paginasi', () => pm.expect(json.data).to.be.an('array'));"
      ]),
      request: {
        auth: authHeader("token"),
        method: "GET",
        header: [jsonHeader],
        url: { raw: "{{base_url}}/mahasiswa", host: ["{{base_url}}"], path: ["mahasiswa"] }
      }
    },
    {
      name: "Create Mahasiswa TRPL",
      event: makePreAndTest(
        [
          "const r = Math.floor(10000 + Math.random() * 90000);",
          "const nim = '24/' + r + '/SV/' + r;",
          "pm.collectionVariables.set('mhs_trpl_nim', nim);"
        ],
        [
          "let json = {}; try { json = pm.response.json(); } catch(e){}",
          "pm.test('Status code 201 Created', () => pm.response.to.have.status(201));",
          "const id = json.mahasiswa_id || (json.data && json.data.mahasiswa_id);",
          "pm.test('mahasiswa_id dihasilkan', () => pm.expect(id).to.be.a('number'));",
          "if (id) {",
          "    pm.collectionVariables.set('mahasiswa_id', id);",
          "    pm.environment.set('mahasiswa_id', id);",
          "}"
        ]
      ),
      request: {
        auth: authHeader("token"),
        method: "POST",
        header: [jsonHeader, { key: "Content-Type", value: "application/json" }],
        body: {
          mode: "raw",
          raw: JSON.stringify({
            nim: "{{mhs_trpl_nim}}",
            nama: "Mahasiswa Uji TRPL",
            angkatan: "2024"
          }, null, 2)
        },
        url: { raw: "{{base_url}}/mahasiswa", host: ["{{base_url}}"], path: ["mahasiswa"] }
      }
    },
    {
      name: "Create Mahasiswa TRIK",
      event: makePreAndTest(
        [
          "const r = Math.floor(10000 + Math.random() * 90000);",
          "pm.collectionVariables.set('mhs_trik_nim', '24/' + r + '/TK/' + r);"
        ],
        [
          "let json = {}; try { json = pm.response.json(); } catch(e){}",
          "pm.test('Status code 201 Created', () => pm.response.to.have.status(201));",
          "const id = json.mahasiswa_id || (json.data && json.data.mahasiswa_id);",
          "if (id) {",
          "    pm.collectionVariables.set('mahasiswa_trik_id', id);",
          "    pm.environment.set('mahasiswa_trik_id', id);",
          "}"
        ]
      ),
      request: {
        auth: authHeader("token_trik"),
        method: "POST",
        header: [jsonHeader, { key: "Content-Type", value: "application/json" }],
        body: {
          mode: "raw",
          raw: JSON.stringify({
            nim: "{{mhs_trik_nim}}",
            nama: "Mahasiswa Uji TRIK",
            angkatan: "2024"
          }, null, 2)
        },
        url: { raw: "{{base_url}}/mahasiswa", host: ["{{base_url}}"], path: ["mahasiswa"] }
      }
    },
    {
      name: "Create Mahasiswa - Ignore Client Prodi",
      event: makePreAndTest(
        [
          "const r = Math.floor(10000 + Math.random() * 90000);",
          "pm.collectionVariables.set('mhs_bypass_nim', '24/' + r + '/SV/' + r);"
        ],
        [
          "let json = {}; try { json = pm.response.json(); } catch(e){}",
          "pm.test('Status code 201 Created', () => pm.response.to.have.status(201));",
          "const m = json.data || json;",
          "pm.test('Prodi ID tetap prodi Admin TRPL (1)', () => pm.expect(m.prodi_id).to.eql(1));"
        ]
      ),
      request: {
        auth: authHeader("token"),
        method: "POST",
        header: [jsonHeader, { key: "Content-Type", value: "application/json" }],
        body: {
          mode: "raw",
          raw: JSON.stringify({
            nim: "{{mhs_bypass_nim}}",
            nama: "Test Mahasiswa Ignore Client Prodi",
            angkatan: 2024,
            prodi_id: 2
          }, null, 2)
        },
        url: { raw: "{{base_url}}/mahasiswa", host: ["{{base_url}}"], path: ["mahasiswa"] }
      }
    },
    {
      name: "Detail Mahasiswa",
      event: makeTest([
        "let json = {}; try { json = pm.response.json(); } catch(e){}",
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));",
        "pm.test('mahasiswa_id sesuai', () => pm.expect(json.mahasiswa_id).to.eql(Number(pm.variables.get('mahasiswa_id'))));"
      ]),
      request: {
        auth: authHeader("token"),
        method: "GET",
        header: [jsonHeader],
        url: { raw: "{{base_url}}/mahasiswa/{{mahasiswa_id}}", host: ["{{base_url}}"], path: ["mahasiswa", "{{mahasiswa_id}}"] }
      }
    },
    {
      name: "Update Mahasiswa",
      event: makeTest([
        "let json = {}; try { json = pm.response.json(); } catch(e){}",
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));",
        "pm.test('Nama berhasil diperbarui', () => pm.expect(json.nama || (json.data && json.data.nama)).to.eql('Mahasiswa Uji TRPL Updated'));"
      ]),
      request: {
        auth: authHeader("token"),
        method: "POST",
        header: [jsonHeader, { key: "Content-Type", value: "application/json" }],
        body: {
          mode: "raw",
          raw: JSON.stringify({
            mahasiswa_id: "{{mahasiswa_id}}",
            nim: "{{mhs_trpl_nim}}",
            nama: "Mahasiswa Uji TRPL Updated"
          }, null, 2)
        },
        url: { raw: "{{base_url}}/mahasiswa/update", host: ["{{base_url}}"], path: ["mahasiswa", "update"] }
      }
    },
    {
      name: "Detail Cross-Prodi (Should 403)",
      event: makeTest([
        "pm.test('Status code 403 Forbidden (Terisolasi Prodi)', () => pm.response.to.have.status(403));"
      ]),
      request: {
        auth: authHeader("token"),
        method: "GET",
        header: [jsonHeader],
        url: { raw: "{{base_url}}/mahasiswa/{{mahasiswa_trik_id}}", host: ["{{base_url}}"], path: ["mahasiswa", "{{mahasiswa_trik_id}}"] }
      }
    },
    {
      name: "Delete Mahasiswa",
      event: makeTest([
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));"
      ]),
      request: {
        auth: authHeader("token"),
        method: "DELETE",
        header: [jsonHeader],
        url: { raw: "{{base_url}}/mahasiswa/{{mahasiswa_id}}", host: ["{{base_url}}"], path: ["mahasiswa", "{{mahasiswa_id}}"] }
      }
    }
  ]
};

// 5. FOLDER 04: MANAJEMEN DOSEN PEMBIMBING
const dosbingFolder = {
  name: "04 - Admin (Manajemen Dosen Pembimbing)",
  item: [
    {
      name: "List Dosen Pembimbing",
      event: makeTest([
        "let json = {}; try { json = pm.response.json(); } catch(e){}",
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));",
        "pm.test('Respons data paginasi', () => pm.expect(json.data).to.be.an('array'));"
      ]),
      request: {
        auth: authHeader("token"),
        method: "GET",
        header: [jsonHeader],
        url: { raw: "{{base_url}}/dosbing", host: ["{{base_url}}"], path: ["dosbing"] }
      }
    },
    {
      name: "Create Dosen Pembimbing",
      event: makePreAndTest(
        [
          "const random10 = Math.floor(1000000000 + Math.random() * 9000000000).toString();",
          "pm.collectionVariables.set('new_nip', '19870101' + random10);"
        ],
        [
          "let json = {}; try { json = pm.response.json(); } catch(e){}",
          "pm.test('Status code 201 Created', () => pm.response.to.have.status(201));",
          "const id = json.dosbing_id || (json.data && json.data.dosbing_id);",
          "pm.test('dosbing_id tersedia', () => pm.expect(id).to.be.a('number'));",
          "if (id) {",
          "    pm.collectionVariables.set('dosbing_id', id);",
          "    pm.environment.set('dosbing_id', id);",
          "}"
        ]
      ),
      request: {
        auth: authHeader("token"),
        method: "POST",
        header: [jsonHeader, { key: "Content-Type", value: "application/json" }],
        body: {
          mode: "raw",
          raw: JSON.stringify({
            nip: "{{new_nip}}",
            nama: "Dr. Ir. Dosen Pembimbing DTEDI, M.Kom."
          }, null, 2)
        },
        url: { raw: "{{base_url}}/dosbing", host: ["{{base_url}}"], path: ["dosbing"] }
      }
    },
    {
      name: "Detail Dosen Pembimbing",
      event: makeTest([
        "let json = {}; try { json = pm.response.json(); } catch(e){}",
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));",
        "pm.test('dosbing_id cocok', () => pm.expect(json.dosbing_id).to.eql(Number(pm.variables.get('dosbing_id'))));"
      ]),
      request: {
        auth: authHeader("token"),
        method: "GET",
        header: [jsonHeader],
        url: { raw: "{{base_url}}/dosbing/{{dosbing_id}}", host: ["{{base_url}}"], path: ["dosbing", "{{dosbing_id}}"] }
      }
    },
    {
      name: "Update Dosen Pembimbing",
      event: makeTest([
        "let json = {}; try { json = pm.response.json(); } catch(e){}",
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));",
        "pm.test('Nama dosen terbarui', () => pm.expect(json.nama || (json.data && json.data.nama)).to.eql('Dr. Ir. Dosen Pembimbing DTEDI, M.Eng.'));"
      ]),
      request: {
        auth: authHeader("token"),
        method: "PUT",
        header: [jsonHeader, { key: "Content-Type", value: "application/json" }],
        body: {
          mode: "raw",
          raw: JSON.stringify({
            nip: "{{new_nip}}",
            nama: "Dr. Ir. Dosen Pembimbing DTEDI, M.Eng."
          }, null, 2)
        },
        url: { raw: "{{base_url}}/dosbing/{{dosbing_id}}", host: ["{{base_url}}"], path: ["dosbing", "{{dosbing_id}}"] }
      }
    },
    {
      name: "Jumlah Dosen Pembimbing",
      event: makeTest([
        "let json = {}; try { json = pm.response.json(); } catch(e){}",
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));",
        "pm.test('Jumlah dosbing tersedia', () => pm.expect(json).to.have.property('jumlah_dosbing'));"
      ]),
      request: {
        auth: authHeader("token"),
        method: "GET",
        header: [jsonHeader],
        url: { raw: "{{base_url}}/dosbing/jumlah", host: ["{{base_url}}"], path: ["dosbing", "jumlah"] }
      }
    },
    {
      name: "Delete Dosen Pembimbing",
      event: makeTest([
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));"
      ]),
      request: {
        auth: authHeader("token"),
        method: "DELETE",
        header: [jsonHeader],
        url: { raw: "{{base_url}}/dosbing/{{dosbing_id}}", host: ["{{base_url}}"], path: ["dosbing", "{{dosbing_id}}"] }
      }
    }
  ]
};

// 6. FOLDER 05: MANAJEMEN MITRA
const mitraFolder = {
  name: "05 - Admin (Manajemen Mitra)",
  item: [
    {
      name: "List Mitra",
      event: makeTest([
        "let json = {}; try { json = pm.response.json(); } catch(e){}",
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));",
        "pm.test('Paginasi mitra tersedia', () => pm.expect(json.data).to.be.an('array'));"
      ]),
      request: {
        auth: authHeader("token"),
        method: "GET",
        header: [jsonHeader],
        url: { raw: "{{base_url}}/mitra", host: ["{{base_url}}"], path: ["mitra"] }
      }
    },
    {
      name: "Create Mitra",
      event: makePreAndTest(
        [
          "const ts = Date.now();",
          "const name = 'PT Solusi Teknologi ' + ts;",
          "const email = 'hrd.' + ts + '@solusitek.co.id';",
          "pm.collectionVariables.set('new_mitra_name', name);",
          "pm.collectionVariables.set('new_mitra_email', email);"
        ],
        [
          "let json = {}; try { json = pm.response.json(); } catch(e){}",
          "pm.test('Status code 201 Created', () => pm.response.to.have.status(201));",
          "const id = json.data ? json.data.mitra_id : json.mitra_id;",
          "pm.test('mitra_id tersedia', () => pm.expect(id).to.be.a('number'));",
          "if (id) {",
          "    pm.collectionVariables.set('mitra_id', id);",
          "    pm.environment.set('mitra_id', id);",
          "}"
        ]
      ),
      request: {
        auth: authHeader("token"),
        method: "POST",
        header: [jsonHeader, { key: "Content-Type", value: "application/json" }],
        body: {
          mode: "raw",
          raw: JSON.stringify({
            nama_mitra: "{{new_mitra_name}}",
            email: "{{new_mitra_email}}",
            narahubung: "Budi Santoso - HR Lead"
          }, null, 2)
        },
        url: { raw: "{{base_url}}/mitra", host: ["{{base_url}}"], path: ["mitra"] }
      }
    },
    {
      name: "Update Mitra",
      event: makeTest([
        "let json = {}; try { json = pm.response.json(); } catch(e){}",
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));",
        "pm.test('Narahubung terbarui', () => pm.expect(json.narahubung || (json.data && json.data.narahubung)).to.eql('Budi Santoso - Senior HR Lead'));"
      ]),
      request: {
        auth: authHeader("token"),
        method: "PUT",
        header: [jsonHeader, { key: "Content-Type", value: "application/json" }],
        body: {
          mode: "raw",
          raw: JSON.stringify({
            nama_mitra: "{{new_mitra_name}}",
            email: "{{new_mitra_email}}",
            narahubung: "Budi Santoso - Senior HR Lead"
          }, null, 2)
        },
        url: { raw: "{{base_url}}/mitra/{{mitra_id}}", host: ["{{base_url}}"], path: ["mitra", "{{mitra_id}}"] }
      }
    },
    {
      name: "Jumlah Mitra",
      event: makeTest([
        "let json = {}; try { json = pm.response.json(); } catch(e){}",
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));",
        "pm.test('Jumlah mitra tersedia', () => pm.expect(json).to.have.property('jumlah_mitra'));"
      ]),
      request: {
        auth: authHeader("token"),
        method: "GET",
        header: [jsonHeader],
        url: { raw: "{{base_url}}/mitra/jumlah", host: ["{{base_url}}"], path: ["mitra", "jumlah"] }
      }
    },
    {
      name: "Delete Mitra",
      event: makeTest([
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));"
      ]),
      request: {
        auth: authHeader("token"),
        method: "DELETE",
        header: [jsonHeader],
        url: { raw: "{{base_url}}/mitra/{{mitra_id}}", host: ["{{base_url}}"], path: ["mitra", "{{mitra_id}}"] }
      }
    }
  ]
};

// 7. FOLDER 06: MANAJEMEN MAGANG
const magangFolder = {
  name: "06 - Admin (Manajemen Magang)",
  item: [
    {
      name: "List Magang (Isolasi Prodi TRPL)",
      event: makeTest([
        "let json = {}; try { json = pm.response.json(); } catch(e){}",
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));",
        "pm.test('Paginasi magang tersedia', () => pm.expect(json.data).to.be.an('array'));"
      ]),
      request: {
        auth: authHeader("token"),
        method: "GET",
        header: [jsonHeader],
        url: { raw: "{{base_url}}/magang", host: ["{{base_url}}"], path: ["magang"] }
      }
    },
    {
      name: "Detail Magang",
      event: makeTest([
        "let json = {}; try { json = pm.response.json(); } catch(e){}",
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));",
        "pm.test('magang_id cocok', () => pm.expect(json.magang_id).to.eql(Number(pm.variables.get('magang_id'))));"
      ]),
      request: {
        auth: authHeader("token"),
        method: "GET",
        header: [jsonHeader],
        url: { raw: "{{base_url}}/magang/{{magang_id}}", host: ["{{base_url}}"], path: ["magang", "{{magang_id}}"] }
      }
    },
    {
      name: "Detail Lengkap Magang",
      event: makeTest([
        "let json = {}; try { json = pm.response.json(); } catch(e){}",
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));",
        "pm.test('Relasi mahasiswa termuat', () => {",
        "    const detail = json.detail || json;",
        "    pm.expect(detail).to.have.property('mahasiswa');",
        "});"
      ]),
      request: {
        auth: authHeader("token"),
        method: "GET",
        header: [jsonHeader],
        url: { raw: "{{base_url}}/magang/{{magang_id}}/detail", host: ["{{base_url}}"], path: ["magang", "{{magang_id}}", "detail"] }
      }
    },
    {
      name: "Update Data Magang (Admin)",
      event: makeTest([
        "let json = {}; try { json = pm.response.json(); } catch(e){}",
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));",
        "pm.test('Role magang terbarui', () => pm.expect(json.role_magang || (json.data && json.data.role_magang)).to.eql('Fullstack Developer Intern'));"
      ]),
      request: {
        auth: authHeader("token"),
        method: "PUT",
        header: [jsonHeader, { key: "Content-Type", value: "application/json" }],
        body: {
          mode: "raw",
          raw: JSON.stringify({
            status_magang: "berlangsung",
            role_magang: "Fullstack Developer Intern"
          }, null, 2)
        },
        url: { raw: "{{base_url}}/magang/{{magang_id}}", host: ["{{base_url}}"], path: ["magang", "{{magang_id}}"] }
      }
    },
    {
      name: "Export Excel Magang",
      event: makeTest([
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));"
      ]),
      request: {
        auth: authHeader("token"),
        method: "GET",
        header: [jsonHeader],
        url: { raw: "{{base_url}}/magang/export/excel", host: ["{{base_url}}"], path: ["magang", "export", "excel"] }
      }
    }
  ]
};

// 8. FOLDER 07: MANAJEMEN JADWAL PRESENTASI
const jadwalFolder = {
  name: "07 - Admin (Manajemen Jadwal Presentasi PI)",
  item: [
    {
      name: "List Jadwal Presentasi (Isolasi Prodi TRPL)",
      event: makeTest([
        "let json = {}; try { json = pm.response.json(); } catch(e){}",
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));",
        "pm.test('Data array jadwal', () => pm.expect(json.data).to.be.an('array'));"
      ]),
      request: {
        auth: authHeader("token"),
        method: "GET",
        header: [jsonHeader],
        url: { raw: "{{base_url}}/jadwal-presentasi", host: ["{{base_url}}"], path: ["jadwal-presentasi"] }
      }
    },
    {
      name: "Create Jadwal Presentasi TRPL",
      event: makeTest([
        "let json = {}; try { json = pm.response.json(); } catch(e){}",
        "pm.test('Status code 201 Created', () => pm.response.to.have.status(201));",
        "const id = json.data ? json.data.jadwal_id : json.jadwal_id;",
        "pm.test('jadwal_id tersedia', () => pm.expect(id).to.be.a('number'));",
        "if (id) {",
        "    pm.collectionVariables.set('jadwal_id', id);",
        "    pm.environment.set('jadwal_id', id);",
        "}"
      ]),
      request: {
        auth: authHeader("token"),
        method: "POST",
        header: [jsonHeader, { key: "Content-Type", value: "application/json" }],
        body: {
          mode: "raw",
          raw: JSON.stringify({
            magang_id: "{{magang_id}}",
            tanggal_presentasi: "2026-11-20",
            waktu_mulai: "09:00",
            waktu_selesai: "10:30",
            ruangan: "Lab Software 201",
            tempat: "Gedung TILC SV UGM",
            keterangan: "Presentasi Akhir Proyek Industri",
            status: "terjadwal"
          }, null, 2)
        },
        url: { raw: "{{base_url}}/jadwal-presentasi", host: ["{{base_url}}"], path: ["jadwal-presentasi"] }
      }
    },
    {
      name: "Detail Jadwal Presentasi",
      event: makeTest([
        "let json = {}; try { json = pm.response.json(); } catch(e){}",
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));",
        "pm.test('jadwal_id cocok', () => pm.expect(json.jadwal_id).to.eql(Number(pm.variables.get('jadwal_id'))));"
      ]),
      request: {
        auth: authHeader("token"),
        method: "GET",
        header: [jsonHeader],
        url: { raw: "{{base_url}}/jadwal-presentasi/{{jadwal_id}}", host: ["{{base_url}}"], path: ["jadwal-presentasi", "{{jadwal_id}}"] }
      }
    },
    {
      name: "Update Jadwal Presentasi",
      event: makeTest([
        "let json = {}; try { json = pm.response.json(); } catch(e){}",
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));",
        "pm.test('Ruangan terbarui', () => pm.expect(json.ruangan || (json.data && json.data.ruangan)).to.eql('Ruang Sidang Utama TILC'));"
      ]),
      request: {
        auth: authHeader("token"),
        method: "PUT",
        header: [jsonHeader, { key: "Content-Type", value: "application/json" }],
        body: {
          mode: "raw",
          raw: JSON.stringify({
            ruangan: "Ruang Sidang Utama TILC",
            keterangan: "Jadwal telah dikonfirmasi"
          }, null, 2)
        },
        url: { raw: "{{base_url}}/jadwal-presentasi/{{jadwal_id}}", host: ["{{base_url}}"], path: ["jadwal-presentasi", "{{jadwal_id}}"] }
      }
    },
    {
      name: "Create Jadwal Cross-Prodi (Should 403)",
      event: makeTest([
        "pm.test('Status code 403 Forbidden (Admin TRPL tidak boleh jadwalkan magang TRIK)', () => pm.response.to.have.status(403));"
      ]),
      request: {
        auth: authHeader("token"),
        method: "POST",
        header: [jsonHeader, { key: "Content-Type", value: "application/json" }],
        body: {
          mode: "raw",
          raw: JSON.stringify({
            magang_id: "{{magang_trik_id}}",
            tanggal_presentasi: "2026-11-20",
            waktu_mulai: "09:00",
            waktu_selesai: "10:30",
            ruangan: "Lab Software 201"
          }, null, 2)
        },
        url: { raw: "{{base_url}}/jadwal-presentasi", host: ["{{base_url}}"], path: ["jadwal-presentasi"] }
      }
    },
    {
      name: "Delete Jadwal Presentasi",
      event: makeTest([
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));"
      ]),
      request: {
        auth: authHeader("token"),
        method: "DELETE",
        header: [jsonHeader],
        url: { raw: "{{base_url}}/jadwal-presentasi/{{jadwal_id}}", host: ["{{base_url}}"], path: ["jadwal-presentasi", "{{jadwal_id}}"] }
      }
    }
  ]
};

// 9. FOLDER 08: CLEANUP & LOGOUT
const cleanupFolder = {
  name: "08 - Sesi & Logout",
  item: [
    {
      name: "Logout Admin TRPL",
      event: makeTest([
        "let json = {}; try { json = pm.response.json(); } catch(e){}",
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));",
        "pm.test('Pesan logout sukses', () => pm.expect(json.message).to.be.oneOf(['Logout berhasil', 'Logged out successfully.']));"
      ]),
      request: {
        auth: authHeader("token"),
        method: "POST",
        header: [jsonHeader],
        url: { raw: "{{base_url}}/auth/logout", host: ["{{base_url}}"], path: ["auth", "logout"] }
      }
    },
    {
      name: "Logout Super Admin",
      event: makeTest([
        "let json = {}; try { json = pm.response.json(); } catch(e){}",
        "pm.test('Status code 200 OK', () => pm.response.to.have.status(200));",
        "pm.test('Pesan logout sukses', () => pm.expect(json.message).to.be.oneOf(['Logout berhasil', 'Logged out successfully.']));"
      ]),
      request: {
        auth: authHeader("super_admin_token"),
        method: "POST",
        header: [jsonHeader],
        url: { raw: "{{base_url}}/auth/logout", host: ["{{base_url}}"], path: ["auth", "logout"] }
      }
    }
  ]
};

col.item = [
  authFolder,
  superAdminFolder,
  adminDashboardFolder,
  mahasiswaFolder,
  dosbingFolder,
  mitraFolder,
  magangFolder,
  jadwalFolder,
  cleanupFolder
];

const destPath = path.join(__dirname, 'SIMAGANG_Admin_SuperAdmin.postman_collection.json');
fs.writeFileSync(destPath, JSON.stringify(col, null, 2), 'utf8');

console.log('✅ Generated refined collection: ' + destPath);
let totalReqs = 0;
col.item.forEach(f => {
  totalReqs += f.item.length;
  console.log(' - ' + f.name + ': ' + f.item.length + ' requests');
});
console.log('Total Requests:', totalReqs);
