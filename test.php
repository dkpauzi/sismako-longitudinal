<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$classes = ['Kelas 9.1', 'Kelas 8.1', 'Kelas 8.2'];
foreach($classes as $cname) {
    $c = App\Models\Classroom::where('name', $cname)->first();
    if($c) {
        $e = App\Models\Enrollment::where('classroom_id', $c->id)->where('academic_period_id', 3)->with('student.user')->first();
        if($e && $e->student && $e->student->user) {
            echo $cname . ' => Nama: ' . $e->student->name . ' | Email: ' . $e->student->user->email . ' | Password: password' . PHP_EOL;
        }
    }
}
