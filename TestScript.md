### TEST CASE 1 : Backup & Purge Data from `system_queue_job` table.

    ## Check original data in the source table & archieve table.
        Current Source  : 79,287,094
        Current Archive : Not exists (automatic create if not exists)

    ## Test Configuration :

        $result = $archiver
                ->backupFrom('system_queue_job')
                ->primaryKey('id')
                ->whereClause("DATE(created_at) <= '2023-04-01'")
                ->mode('BP')
                ->chunk('50000')
                ->onDebug()
                ->run();

    ## Test Data :
        2023-04-01      97,496
        2023-03-31      80,041
        2023-03-30      87,721
        2023-03-29      53,478
        2023-03-28      109,232
        2023-03-27      115,723
        2023-03-26      105,917
        2023-03-25      37,417
        2023-03-24      99,338
        2023-03-23      114,437
        2023-03-22      129,171
        2023-03-21      32,542
        2023-03-20      130,199
        2023-03-19      33,364

    ## Expected Result :-
        Backup : 1,226,076
        Purge : 1,226,076

    ## Actual Result :-

        {
            "status": "completed",
            "mode": "BP",
            "table": "system_queue_job",
            "backup_table": "system_queue_job_ARC",
            "total": 1226076,
            "processed": {
                "backup": 1226076,
                "purge": 1226076
            },
            "messages": "Records processed successfully",
            "execution_time": "00:04:57.792",
            "threads": 1,
            "memory": {
                "initial": "12 MB",
                "final": "12 MB",
                "peak": "12 MB",
                "used": "0 B"
            }
        }

    ## Verify Data After Running Test :
        Current Source  : 78,061,018
        Current Archive : 1,226,076

    ## Log File : 2025-02-16_TC01.log

    ## Result : PASSED ✅

====================================================================================================

### TEST CASE 1.1 : Re-run test case 1.

    ## Check original data in the source table & archieve table.
        Current Source  : 78,061,018
        Current Archive : 1,226,076

    ## Test Configuration :

        $result = $archiver
                ->backupFrom('system_queue_job')
                ->primaryKey('id')
                ->whereClause("DATE(created_at) <= '2023-04-01'")
                ->mode('BP')
                ->chunk('50000')
                ->onDebug()
                ->run();

    ## Expected Result :-
        Backup : 0
        Purge : 0

    ## Actual Result :-

        {
            "status": "completed",
            "mode": "BP",
            "table": "system_queue_job",
            "backup_table": "system_queue_job_ARC",
            "total": 0,
            "processed": {
                "backup": 0,
                "purge": 0
            },
            "messages": "No records found to process",
            "execution_time": "00:02:02.891"
        }

    ## Verify Data After Running Test :
        Current Source  : 78,061,018
        Current Archive : 1,226,076

    ## Log File : 2025-02-16_TC01-2nd-Run.log

    ## Result : PASSED ✅

    ====================================================================================================