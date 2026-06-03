<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);
require 'backend-php/config/db.php';
require 'backend-php/app/Models/StrategicBudgetingAdminModel.php';
$model = new \App\Models\StrategicBudgetingAdminModel($conn);
$payload = [
  'SECTOR' => ['Decision' => 'NOT_MAPPED', 'SegmentNo' => 0, 'Notes' => null],
  'PROGRAM' => ['Decision' => '', 'SegmentNo' => 0, 'Notes' => null],
  'SUBPROGRAM' => ['Decision' => '', 'SegmentNo' => 0, 'Notes' => null],
  'ECONOMIC' => ['Decision' => '', 'SegmentNo' => 0, 'Notes' => null],
  'FUNDING_TYPE' => ['Decision' => '', 'SegmentNo' => 0, 'Notes' => null],
  'FUNDING_SOURCE' => ['Decision' => '', 'SegmentNo' => 0, 'Notes' => null],
  'OBJECTIVE' => ['Decision' => '', 'SegmentNo' => 0, 'Notes' => null],
  'OUTPUT' => ['Decision' => '', 'SegmentNo' => 0, 'Notes' => null],
  'ACTIVITY' => ['Decision' => '', 'SegmentNo' => 0, 'Notes' => null],
  'INDICATOR' => ['Decision' => '', 'SegmentNo' => 0, 'Notes' => null],
  'TARGET' => ['Decision' => '', 'SegmentNo' => 0, 'Notes' => null],
];
try {
  $model->saveStrategicSegmentMappings(2026, $payload, 1);
  echo "OK\n";
} catch (Throwable $e) {
  echo get_class($e), "\n";
  echo $e->getMessage(), "\n";
}
