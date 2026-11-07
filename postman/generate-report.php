<?php
/**
 * Membaca output JUnit XML dari PHPUnit dan menghasilkan laporan HTML
 * bergaya Newman untuk test Super Admin & Admin Prodi.
 *
 * Jalankan: php postman/generate-report.php
 */

$xmlPath = __DIR__ . '/test-results-admin.xml';
$outputPath = __DIR__ . '/laporan-superadmin-admin.html';

if (! file_exists($xmlPath)) {
    echo "File JUnit XML tidak ditemukan: {$xmlPath}\n";
    echo "Jalankan dulu: php artisan test --filter=\"SuperAdminTest|CrossProdiAuthorizationTest|JadwalPresentasiTest|MahasiswaProdiTest\" --log-junit=postman/test-results-admin.xml\n";
    exit(1);
}

$xml = simplexml_load_file($xmlPath);

// Parse test suites
$suites = [];
$totalTests = 0;
$totalAssertions = 0;
$totalFailed = 0;
$totalSkipped = 0;
$totalErrors = 0;
$totalTime = 0;

foreach ($xml->testsuite as $topSuite) {
    foreach ($topSuite->testsuite ?? [$topSuite] as $suite) {
        $suiteName = (string) $suite['name'];
        $suiteTests = [];

        foreach ($suite->testcase ?? [] as $testcase) {
            $testName = (string) $testcase['name'];
            $className = (string) $testcase['class'];
            $time = (float) $testcase['time'];
            $assertions = (int) $testcase['assertions'];

            $status = 'passed';
            $failMessage = '';

            if (isset($testcase->failure)) {
                $status = 'failed';
                $failMessage = (string) $testcase->failure;
                $totalFailed++;
            } elseif (isset($testcase->error)) {
                $status = 'error';
                $failMessage = (string) $testcase->error;
                $totalErrors++;
            } elseif (isset($testcase->skipped)) {
                $status = 'skipped';
                $totalSkipped++;
            }

            $totalTests++;
            $totalAssertions += $assertions;
            $totalTime += $time;

            $suiteTests[] = [
                'name' => $testName,
                'class' => $className,
                'time' => $time,
                'assertions' => $assertions,
                'status' => $status,
                'failMessage' => $failMessage,
            ];
        }

        // Also check nested testsuites
        foreach ($suite->testsuite ?? [] as $nested) {
            $nestedSuiteName = (string) $nested['name'];
            foreach ($nested->testcase ?? [] as $testcase) {
                $testName = (string) $testcase['name'];
                $className = (string) $testcase['class'];
                $time = (float) $testcase['time'];
                $assertions = (int) $testcase['assertions'];

                $status = 'passed';
                $failMessage = '';

                if (isset($testcase->failure)) {
                    $status = 'failed';
                    $failMessage = (string) $testcase->failure;
                    $totalFailed++;
                } elseif (isset($testcase->error)) {
                    $status = 'error';
                    $failMessage = (string) $testcase->error;
                    $totalErrors++;
                } elseif (isset($testcase->skipped)) {
                    $status = 'skipped';
                    $totalSkipped++;
                }

                $totalTests++;
                $totalAssertions += $assertions;
                $totalTime += $time;

                $suiteTests[] = [
                    'name' => $testName,
                    'class' => $nestedSuiteName,
                    'time' => $time,
                    'assertions' => $assertions,
                    'status' => $status,
                    'failMessage' => $failMessage,
                ];
            }
        }

        if (count($suiteTests) > 0) {
            $suites[$suiteName] = $suiteTests;
        }
    }
}

$totalPassed = $totalTests - $totalFailed - $totalSkipped - $totalErrors;
$timestamp = date('l, d F Y H:i:s');
$timeFormatted = number_format($totalTime, 1) . 's';

// Group by test class
$groupedTests = [];
foreach ($suites as $suiteName => $tests) {
    foreach ($tests as $test) {
        $cls = $test['class'];
        if (! isset($groupedTests[$cls])) {
            $groupedTests[$cls] = [];
        }
        $groupedTests[$cls][] = $test;
    }
}

// Generate HTML
ob_start();
?>
<!DOCTYPE html>
<html lang="en" style="overflow-y: scroll;">
<head>
  <meta charset="UTF-8">
  <title>SIMAGANG — Super Admin & Admin Test Report</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.13.0/css/all.min.css">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css">
<style>
.theme-dark {
    --background-color: #222;
    --text-color: #ccd2d8;
    --success: #3c9372;
    --failure: #c24a3f;
    --warning: #d28c23;
    --info: #4083b6;
    --card-bg: #444;
    --card-footer: #4f5758;
    --hov-text: #d2dae5;
}
.theme-light {
    --text-color: #000000;
    --success: #42a745;
    --failure: #dc3544;
    --warning: #f4c10b;
    --info: #49a1b8;
    --card-bg: #f7f7f7;
    --hov-text: #fff;
}
body {
  padding-top:30px;
  background-color: var(--background-color)!important;
  color: var(--text-color);
}
body.theme-dark .card-header { background-color: #444; }
body.theme-light .card-header { background-color: #f7f7f7; }
body.theme-dark .card-body { background-color: #444; }
body.theme-light .card-body { background-color: #f7f7f7; }
.card { overflow:hidden; }
.card-body .rotate {
  z-index: 8; float: right; height: 100%;
}
.card-body .rotate i {
  color: #14141426; position: absolute; right: -10px; bottom: 0;
  display: block; transform: rotate(-44deg);
}
.bg-info { background-color: var(--info)!important; }
.bg-success { background-color: var(--success)!important; }
.bg-warning { background-color: var(--warning)!important; }
.bg-danger, body.theme-dark .bg-danger, body.theme-light .bg-danger { background-color: var(--failure)!important; }
.badge-outline-success { color: var(--success); border: 1px solid var(--success); background-color: transparent; }
body.theme-dark .table .thead-light th { background-color: #4f5858!important; color: #ccd2d8!important; }
body.theme-dark h1 { color: #ccd2da; }
body.theme-dark .text-dark { color: #d1dae4!important; }
h4 { color: var(--hov-text); }
.switch { position: relative; display: inline-block; width: 44px; height: 20px; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider {
  position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
  background-color: #ccc; transition: 0.4s;
}
.slider:before {
  position: absolute; content: ""; height: 20px; width: 20px; left: 0px;
  top: 0; bottom: 0; margin: auto 0; transition: 0.4s;
  box-shadow: 0 0px 15px #2020203d; background: white;
}
input:checked + .slider { background-color: #4083b6; }
input:checked + .slider:before { transform: translateX(24px); }
.slider.round { border-radius: 34px; }
.slider.round:before { border-radius: 50%; }
.test-passed { color: var(--success); font-weight: bold; }
.test-failed { color: var(--failure); font-weight: bold; }
.test-skipped { color: var(--warning); font-weight: bold; }
.card-header .fa { transition: .3s transform ease-in-out; }
.card-header .collapsed .fa { transform: rotate(90deg); }
.alert-success { background-color: var(--success)!important; }
.alert-danger { background-color: var(--failure)!important; }
</style>
</head>
<body class="theme-dark">
<script>
function setTheme(t){localStorage.setItem('theme',t);document.body.className=t;}
function toggleTheme(){setTheme(localStorage.getItem('theme')==='theme-light'?'theme-dark':'theme-light');}
</script>
<div class="container">
  <div class="container">
    <label>Light</label>
    <label class="switch"><input type="checkbox" onchange="toggleTheme()" id="slider"><span class="slider round"></span></label>
    <label>Dark</label>
  </div>

  <div class="card">
    <div class="card-header">
      <ul class="nav nav-pills mb-3 nav-justified" id="pills-tab" role="tablist">
        <li class="nav-item bg-info active"><a class="nav-link text-white" data-toggle="pill" href="#pills-summary">Summary</a></li>
        <li class="nav-item bg-success"><a class="nav-link text-white" data-toggle="pill" href="#pills-requests">Total Tests <span class="badge badge-light"><?= $totalTests ?></span></a></li>
        <li class="nav-item bg-danger"><a class="nav-link text-white" data-toggle="pill" href="#pills-failed">Failed Tests <span class="badge badge-light"><?= $totalFailed ?></span></a></li>
        <li class="nav-item bg-warning"><a class="nav-link text-white" data-toggle="pill" href="#pills-skipped">Skipped Tests <span class="badge badge-light"><?= $totalSkipped ?></span></a></li>
      </ul>

    <div class="tab-content">
      <!-- SUMMARY -->
      <div class="tab-pane fade show active" id="pills-summary">
        <div class="row">
          <div class="col-md-12">
            <h1 class="display-3 text-center">SIMAGANG Test Report</h1>
            <h4 class="text-center" style="color: var(--info);">Super Admin & Admin Prodi</h4>
            <h5 class="text-center"><?= $timestamp ?></h5>
            <div class="row mt-4">
              <div class="col-lg-3 col-md-6">
                <div class="card text-white"><div class="card-body bg-success">
                  <div class="rotate"><i class="fa fa-check-circle fa-5x"></i></div>
                  <h6 class="text-uppercase">Total Passed</h6>
                  <h1 class="display-1"><?= $totalPassed ?></h1>
                </div></div>
              </div>
              <div class="col-lg-3 col-md-6">
                <div class="card text-white"><div class="card-body bg-success">
                  <div class="rotate"><i class="fa fa-list fa-4x"></i></div>
                  <h6 class="text-uppercase">Total Assertions</h6>
                  <h1 class="display-1"><?= $totalAssertions ?></h1>
                </div></div>
              </div>
              <div class="col-lg-3 col-md-6">
                <div class="card text-white"><div class="card-body <?= $totalFailed > 0 ? 'bg-danger' : 'bg-success' ?>">
                  <div class="rotate"><i class="fa fa-times-circle fa-5x"></i></div>
                  <h6 class="text-uppercase">Total Failed</h6>
                  <h1 class="display-1"><?= $totalFailed ?></h1>
                </div></div>
              </div>
              <div class="col-lg-3 col-md-6">
                <div class="card text-white"><div class="card-body <?= $totalSkipped > 0 ? 'bg-warning' : 'bg-success' ?>">
                  <div class="rotate"><i class="fa fa-share fa-5x"></i></div>
                  <h6 class="text-uppercase">Total Skipped</h6>
                  <h1 class="display-1"><?= $totalSkipped ?></h1>
                </div></div>
              </div>
            </div>
            <hr>
            <div class="row">
              <div class="col-sm-12 mb-3">
                <div class="card border-info"><div class="card-body">
                  <h5 class="card-title text-uppercase text-white text-center bg-info">Collection Information</h5>
                  <span><i class="fas fa-file-code"></i></span><strong> Collection:</strong> SIMAGANG — Super Admin &amp; Admin Prodi<br>
                  <span><i class="fas fa-cogs"></i></span><strong> Test Runner:</strong> PHPUnit (automated feature tests)<br>
                  <span><i class="fas fa-shield-alt"></i></span><strong> Scope:</strong> Login, RBAC, CRUD Admin Prodi, Dashboard, Jadwal Presentasi, Cross-Prodi Authorization, Mahasiswa Multi-Prodi<br>
                </div></div>
              </div>
            </div>
            <div class="row">
              <div class="col-sm-12 mb-3">
                <div class="card border-info"><div class="card-body">
                  <h5 class="card-title text-uppercase text-white text-center bg-info">Collection Description</h5>
                  <div style="max-height:350px;overflow-y:auto;">
                    <p>Laporan pengujian otomatis API Super Admin &amp; Admin Prodi SIMAGANG DTEDI, mencakup:</p>
                    <ul>
                      <li><strong>SuperAdminTest</strong> — Login super admin, proteksi registrasi publik, RBAC route super admin, deaktivasi akun, CRUD admin prodi, hash password, dashboard agregat &amp; filter prodi</li>
                      <li><strong>CrossProdiAuthorizationTest</strong> — Isolasi lintas prodi untuk magang/logbook/dokumen/laporan, proteksi download file, pembatasan role dosbing</li>
                      <li><strong>JadwalPresentasiTest</strong> — CRUD jadwal presentasi admin prodi, proteksi lintas prodi, akses super admin, isolasi mahasiswa, penolakan dosbing/mitra, jadwal historis, ON DELETE SET NULL</li>
                      <li><strong>MahasiswaProdiTest</strong> — Relasi admin-prodi, auto-assign prodi mahasiswa, isolasi data mahasiswa lintas prodi, proteksi update/delete lintas prodi</li>
                    </ul>
                    <p>Semua test menggunakan RefreshDatabase/DatabaseTransactions dan tidak menyentuh database produksi.</p>
                  </div>
                </div></div>
              </div>
            </div>
            <div class="row">
              <div class="col-sm-12 mb-3">
                <div class="card border-info"><div class="card-body">
                  <h5 class="card-title text-uppercase text-white text-center bg-info">Timings and Data</h5>
                  <span><i class="fas fa-stopwatch"></i></span><strong> Total run duration:</strong> <?= $timeFormatted ?><br>
                  <span><i class="fas fa-clipboard-check"></i></span><strong> Total assertions:</strong> <?= $totalAssertions ?><br>
                  <span><i class="fas fa-vial"></i></span><strong> Total test cases:</strong> <?= $totalTests ?><br>
                </div></div>
              </div>
            </div>
            <div class="row">
              <div class="col-sm-12 mb-3">
                <div class="table-responsive">
                  <table class="table table-striped table-bordered">
                    <thead class="thead-inverse"><tr class="text-center">
                      <th class="text-uppercase">Summary Item</th>
                      <th class="text-uppercase">Total</th>
                      <th class="text-uppercase">Failed</th>
                    </tr></thead>
                    <tbody>
                      <tr><td>Test Cases</td><td class="text-center"><?= $totalTests ?></td><td class="text-center"><?= $totalFailed ?></td></tr>
                      <tr><td>Assertions</td><td class="text-center"><?= $totalAssertions ?></td><td class="text-center">-</td></tr>
                      <tr><td>Errors</td><td class="text-center"><?= $totalErrors ?></td><td class="text-center">-</td></tr>
                      <tr><td>Skipped</td><td class="text-center"><?= $totalSkipped ?></td><td class="text-center">-</td></tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- FAILED -->
      <div class="tab-pane fade" id="pills-failed">
<?php if ($totalFailed === 0): ?>
        <div class="alert alert-success text-uppercase text-center">
          <br><br><h1 class="text-center">There are no failed tests <span><i class="far fa-thumbs-up"></i></span></h1><br><br>
        </div>
<?php else: ?>
<?php foreach ($groupedTests as $className => $tests): ?>
<?php foreach ($tests as $t): if ($t['status'] !== 'failed') continue; ?>
        <div class="card mb-2"><div class="card-body bg-danger text-white">
          <strong><?= htmlspecialchars($className) ?></strong><br>
          <?= htmlspecialchars($t['name']) ?><br>
          <small><?= htmlspecialchars(substr($t['failMessage'], 0, 500)) ?></small>
        </div></div>
<?php endforeach; endforeach; ?>
<?php endif; ?>
      </div>

      <!-- SKIPPED -->
      <div class="tab-pane fade" id="pills-skipped">
<?php if ($totalSkipped === 0): ?>
        <div class="alert alert-success text-uppercase text-center">
          <br><br><h1 class="text-center">There are no skipped tests <span><i class="far fa-thumbs-up"></i></span></h1><br><br>
        </div>
<?php else: ?>
<?php foreach ($groupedTests as $className => $tests): ?>
<?php foreach ($tests as $t): if ($t['status'] !== 'skipped') continue; ?>
        <div class="card mb-2"><div class="card-body bg-warning text-white">
          <strong><?= htmlspecialchars($className) ?></strong> — <?= htmlspecialchars($t['name']) ?>
        </div></div>
<?php endforeach; endforeach; ?>
<?php endif; ?>
      </div>

      <!-- ALL TESTS -->
      <div class="tab-pane fade" id="pills-requests">
        <h5 class="text-uppercase mt-3 mb-3"><?= count($groupedTests) ?> Test Suites</h5>
<?php $suiteIndex = 0; foreach ($groupedTests as $className => $tests):
    $suitePassed = count(array_filter($tests, fn($t) => $t['status'] === 'passed'));
    $suiteFailed = count(array_filter($tests, fn($t) => $t['status'] === 'failed'));
    $suiteSkipped_c = count(array_filter($tests, fn($t) => $t['status'] === 'skipped'));
    $suiteTotal = count($tests);
    $suiteAllPass = $suiteFailed === 0;
    $suiteId = 'suite-' . md5($className);
    $shortName = str_replace('Tests\\Feature\\', '', $className);
    // Map class names to human-readable descriptions
    $suiteDescriptions = [
        'SuperAdminTest' => 'Login, RBAC, CRUD Admin Prodi, Dashboard Super Admin (Agregat & Filter Prodi), Toggle Status, Hash Password, Proteksi Registrasi Publik',
        'CrossProdiAuthorizationTest' => 'Isolasi data magang/logbook/dokumen/laporan lintas prodi, Proteksi download file privat & legacy, Pembatasan role dosbing',
        'JadwalPresentasiTest' => 'CRUD jadwal presentasi per prodi, Akses Super Admin lintas prodi, Isolasi mahasiswa, Penolakan dosbing/mitra, Jadwal historis, ON DELETE SET NULL',
        'MahasiswaProdiTest' => 'Relasi admin-prodi setelah seeding, Auto-assign prodi_id mahasiswa, Isolasi index/detail/update/delete lintas prodi, Penolakan inject prodi_id',
    ];
    $desc = $suiteDescriptions[$shortName] ?? '';
?>
        <div class="alert <?= $suiteAllPass ? 'alert-dark' : 'alert-danger' ?> text-uppercase text-center">
          <a data-toggle="collapse" href="#" data-target="#<?= $suiteId ?>" class="collapsed text-dark z-block" style="display:block;">
            <i class="fas fa-info-circle float-left <?= $suiteAllPass ? 'test-passed' : 'test-failed' ?>"></i>
            <?= htmlspecialchars($shortName) ?> — <?= $suiteTotal ?> Test(s)
            <i class="float-right fa fa-chevron-down" style="padding-top:5px;"></i>
          </a>
        </div>
        <div id="<?= $suiteId ?>" class="collapse">
<?php if ($desc): ?>
          <div class="card mb-3 border-info"><div class="card-body">
            <h5 class="card-title text-uppercase text-white text-center bg-info">Suite Description</h5>
            <p><?= htmlspecialchars($desc) ?></p>
          </div></div>
<?php endif; ?>
          <div class="table-responsive mb-4">
            <table class="table table-hover table-bordered">
              <thead class="thead-light text-center">
                <tr><th>#</th><th>Test Name</th><th>Assertions</th><th>Time</th><th>Status</th></tr>
              </thead>
              <tbody>
<?php $idx = 0; foreach ($tests as $t): $idx++; ?>
                <tr class="<?= $t['status'] === 'failed' ? 'table-danger' : ($t['status'] === 'skipped' ? 'table-warning' : '') ?>">
                  <td class="text-center"><?= $idx ?></td>
                  <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', preg_replace('/^test_/', '', $t['name'])))) ?></td>
                  <td class="text-center"><?= $t['assertions'] ?></td>
                  <td class="text-center"><?= number_format($t['time'] * 1000, 0) ?>ms</td>
                  <td class="text-center">
<?php if ($t['status'] === 'passed'): ?>
                    <span class="badge badge-success"><i class="fas fa-check"></i> PASS</span>
<?php elseif ($t['status'] === 'failed'): ?>
                    <span class="badge badge-danger"><i class="fas fa-times"></i> FAIL</span>
<?php else: ?>
                    <span class="badge badge-warning"><i class="fas fa-forward"></i> SKIP</span>
<?php endif; ?>
                  </td>
                </tr>
<?php if ($t['status'] === 'failed' && $t['failMessage']): ?>
                <tr class="table-danger"><td colspan="5"><small><pre><?= htmlspecialchars(substr($t['failMessage'], 0, 500)) ?></pre></small></td></tr>
<?php endif; ?>
<?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr class="bg-light"><td></td><td><strong>Total</strong></td><td class="text-center"><strong><?= array_sum(array_column($tests, 'assertions')) ?></strong></td><td class="text-center"><strong><?= number_format(array_sum(array_column($tests, 'time')) * 1000, 0) ?>ms</strong></td><td class="text-center">
                  <span class="test-passed"><?= $suitePassed ?> ✓</span>
                  <?= $suiteFailed > 0 ? '<span class="test-failed">' . $suiteFailed . ' ✗</span>' : '' ?>
                  <?= $suiteSkipped_c > 0 ? '<span class="test-skipped">' . $suiteSkipped_c . ' ⊘</span>' : '' ?>
                </td></tr>
              </tfoot>
            </table>
          </div>
        </div>
<?php $suiteIndex++; endforeach; ?>

        <!-- Test Pass Percentage -->
        <div class="row mt-3">
          <div class="col-sm-12 mb-3">
            <div class="card border-info"><div class="card-body">
              <h5 class="card-title text-uppercase text-white text-center bg-info">Overall Test Pass Percentage</h5>
              <div class="progress" style="height: 40px;">
<?php $pct = $totalTests > 0 ? round(($totalPassed / $totalTests) * 100) : 0; ?>
                <div class="progress-bar <?= $pct == 100 ? 'bg-success' : 'bg-danger' ?>" style="width: <?= $pct ?>%" role="progressbar">
                  <h5 style="padding-top:5px;"><strong><?= $pct ?> %</strong></h5>
                </div>
              </div>
            </div></div>
          </div>
        </div>
      </div>
    </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js"></script>
<script>
// Set theme from localStorage
(function() {
    var t = localStorage.getItem('theme');
    if (t) document.body.className = t;
})();
</script>
</body>
</html>
<?php
$html = ob_get_clean();
file_put_contents($outputPath, $html);
echo "✅ Report generated: {$outputPath}\n";
echo "   Tests: {$totalTests} | Passed: {$totalPassed} | Failed: {$totalFailed} | Skipped: {$totalSkipped}\n";
echo "   Assertions: {$totalAssertions} | Duration: {$timeFormatted}\n";
