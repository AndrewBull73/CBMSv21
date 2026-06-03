# 03 Phase Testing

Use this phase after Phase 02 configuration is underway or complete.

## Run Order

1. [04_testing_readiness_note.md](/C:/xampp82/htdocs/CBMSv21/testing/inittest-pack/03_phase_testing/04_testing_readiness_note.md)
2. [03_uat_defect_tracker_template.csv](/C:/xampp82/htdocs/CBMSv21/testing/inittest-pack/03_phase_testing/03_uat_defect_tracker_template.csv)
3. [05_uat_verification_library_fy2026_v6.sql](/C:/xampp82/htdocs/CBMSv21/testing/inittest-pack/03_phase_testing/05_uat_verification_library_fy2026_v6.sql)
4. [02_uat_smoke_test_pack.md](/C:/xampp82/htdocs/CBMSv21/testing/inittest-pack/03_phase_testing/02_uat_smoke_test_pack.md)
5. [01_uat_baseline_pack_fy2026_v6.md](/C:/xampp82/htdocs/CBMSv21/testing/inittest-pack/03_phase_testing/01_uat_baseline_pack_fy2026_v6.md)

## Screen Test Start Point

Start with the built-in scripts in this order:

1. `Base Configuration Readiness Gate`
2. `System Settings Baseline Review`
3. `Data Object Codes And Scope Readiness`
4. `Segment Catalogue Setup Review`
5. `Segment Values Baseline Review`
6. `Workflow Engine Foundation Review`

## Notes

- The fresh-start build begins on `FY 2026 / Version 1`.
- The existing `FY 2026 / Version 6` documents remain useful as later-cycle regression packs once the environment has progressed beyond the initial setup state.

## Exit Criteria

- smoke checks completed
- defects logged consistently
- verification SQL available for issue confirmation
