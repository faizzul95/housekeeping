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

### TEST CASE 2 : Backup & Purge Data from `system_queue_job` table.

    ## Check original data in the source table & archieve table.
        Current Source  : 78,061,018
        Current Archive : 1,226,076

    ## Test Configuration :

        $result = $archiver
                ->backupFrom('system_queue_job')
                ->primaryKey('id')
                ->whereClause("DATE(created_at) <= '2023-06-26'")
                ->mode('BP')
                ->chunk('50000')
                ->onDebug()
                ->run();

    ## Test Data :
        2023-06-26      116854
        2023-06-25      90524
        2023-06-24      82060
        2023-06-23      118727
        2023-06-22      77455
        2023-06-21      136093
        2023-06-20      53098
        2023-06-19      87215
        2023-06-18      131782
        2023-06-17      127260
        2023-06-16      95956
        2023-06-15      78000
        2023-06-14      82134
        2023-06-13      31667
        2023-06-12      141935
        2023-06-11      94671
        2023-06-10      27552
        2023-06-09      83749
        2023-06-08      66088
        2023-06-07      59191
        2023-06-06      77695
        2023-06-05      65898
        2023-06-04      76408
        2023-06-03      39343
        2023-06-02      72493
        2023-06-01      99438
        2023-05-31      134709
        2023-05-30      105229
        2023-05-29      102018
        2023-05-28      49401
        2023-05-27      45955
        2023-05-26      61573
        2023-05-25      24998
        2023-05-24      45272
        2023-05-23      131367
        2023-05-22      126016
        2023-05-21      90978
        2023-05-20      56845
        2023-05-19      116289
        2023-05-18      140911
        2023-05-17      85687
        2023-05-16      110701
        2023-05-15      26445
        2023-05-14      30123
        2023-05-13      51283
        2023-05-12      21042
        2023-05-11      56365
        2023-05-10      73696
        2023-05-09      54388
        2023-05-08      30849
        2023-05-07      96085
        2023-05-06      117877
        2023-05-05      31128
        2023-05-04      32009
        2023-05-03      46665
        2023-05-02      117295
        2023-05-01      51480
        2023-04-30      135517
        2023-04-29      128144
        2023-04-28      89169
        2023-04-27      41411
        2023-04-26      44552
        2023-04-25      78528
        2023-04-24      113984
        2023-04-23      64332
        2023-04-22      84712
        2023-04-21      85564
        2023-04-20      28680
        2023-04-19      116714      
        2023-04-18      102524
        2023-04-17      142482
        2023-04-16      134835
        2023-04-15      101727
        2023-04-14      84132
        2023-04-13      95478
        2023-04-12      79995
        2023-04-11      93542
        2023-04-10      82724
        2023-04-09      112996
        2023-04-08      46806
        2023-04-07      125043
        2023-04-06      89795
        2023-04-05      53849
        2023-04-04      104858
        2023-04-03      92744
        2023-04-02      129146

    ## Expected Result :-
        Backup : 7,161,948
        Purge : 7,161,948

    ## Actual Result :-

        {
            "status": "completed",
            "mode": "BP",
            "table": "system_queue_job",
            "backup_table": "system_queue_job_ARC",
            "total": 7161948,
            "processed": {
                "backup": 7161948,
                "purge": 7161948
            },
            "messages": "Records processed successfully",
            "execution_time": "02:56:24.112",
            "threads": 1,
            "memory": {
                "initial": "12 MB",
                "final": "12 MB",
                "peak": "12 MB",
                "used": "0 B"
            }
        }

    ## Verify Data After Running Test :
        Current Source  : 70,899,070
        Current Archive : 8,388,024

    ## Log File : 2025-02-21_TC01.log

    ## Result : PASSED ✅

====================================================================================================

### TEST CASE 2.1 : Re-run test case 2.

    ## Check original data in the source table & archieve table.
        Current Source  : 70,899,070
        Current Archive : 8,388,024

    ## Test Configuration :

        $result = $archiver
                ->backupFrom('system_queue_job')
                ->primaryKey('id')
                ->whereClause("DATE(created_at) <= '2023-06-26'")
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
            "execution_time": "00:01:03.815"
        }

    ## Verify Data After Running Test :
        Current Source  : 70,899,070
        Current Archive : 8,388,024

    ## Log File : 2025-02-21_TC01_2nd-Run.log

    ## Result : PASSED ✅

====================================================================================================

### TEST CASE 3 : Backup & Purge Data from `system_queue_job` table.

    ## Check original data in the source table & archieve table.
        Current Source  : 70,899,070
        Current Archive : 8,388,024

    ## Test Configuration :

        $result = $archiver
                ->backupFrom('system_queue_job')
                ->primaryKey('id')
                ->whereClause("DATE(created_at) = '2023-06-27'")
                ->mode('BP')
                ->chunk('50000')
                ->onDebug()
                ->run();

    ## Test Data :
        2023-06-27      32914

    ## Expected Result :-
        Backup : 32,914
        Purge : 32,914

    ## Actual Result :-

        {
            "status": "completed",
            "mode": "BP",
            "table": "system_queue_job",
            "backup_table": "system_queue_job_ARC",
            "total": 32914,
            "processed": {
                "backup": 32914,
                "purge": 32914
            },
            "messages": "Records processed successfully",
            "execution_time": "00:02:27.236",
            "threads": 1,
            "memory": {
                "initial": "6 MB",
                "final": "6 MB",
                "peak": "6 MB",
                "used": "0 B"
            }
        }

    ## Verify Data After Running Test :
        Current Source  : 70,866,156
        Current Archive : 8,420,938

    ## Log File : 2025-02-21_TC02.log

    ## Result : PASSED ✅

====================================================================================================

### TEST CASE 4 : Backup & Purge Data from `system_queue_job` table.

    ## Check original data in the source table & archieve table.
        Current Source  : 70,866,156
        Current Archive : 8,420,938

    ## Test Configuration :

        $result = $archiver
                ->backupFrom('system_queue_job')
                ->primaryKey('id')
                ->whereClause("DATE(created_at) <= '2023-07-31'")
                ->mode('BP')
                ->chunk('50000')
                ->onDebug()
                ->run();

    ## Test Data :
        2023-07-31      112235
        2023-07-30      83028
        2023-07-29      58434
        2023-07-28      23088
        2023-07-27      45139
        2023-07-26      136430
        2023-07-25      26730
        2023-07-24      79364
        2023-07-23      46628
        2023-07-22      100048
        2023-07-21      90355
        2023-07-20      131632
        2023-07-19      117095
        2023-07-18      45577
        2023-07-17      106603
        2023-07-16      126282
        2023-07-15      41598
        2023-07-14      59149
        2023-07-13      25951
        2023-07-12      57309
        2023-07-11      63692
        2023-07-10      126534
        2023-07-09      46592
        2023-07-08      83358
        2023-07-07      132016
        2023-07-06      140002
        2023-07-05      33962
        2023-07-04      104809
        2023-07-03      27153
        2023-07-02      51344
        2023-07-01      30259
        2023-06-30      102264
        2023-06-29      25539
        2023-06-28      50906

    ## Expected Result :-
        Backup : 2,531,105
        Purge : 2,531,105

    ## Actual Result :-
    
        {
            "status": "completed",
            "mode": "BP",
            "table": "system_queue_job",
            "backup_table": "system_queue_job_ARC",
            "total": 2531105,
            "processed": {
                "backup": 2531105,
                "purge": 2531105
            },
            "messages": "Records processed successfully",
            "execution_time": "01:19:57.139",
            "threads": 1,
            "memory": {
                "initial": "12 MB",
                "final": "12 MB",
                "peak": "12 MB",
                "used": "0 B"
            }
        }

    ## Verify Data After Running Test :
        Current Source  : 68,335,051
        Current Archive : 10,952,043

    ## Log File : 2025-02-21_TC03.log

    ## Result : PASSED ✅

====================================================================================================

### TEST CASE 5 : Backup & Purge Data from `system_queue_job` table.

    ## Check original data in the source table & archieve table.
        Current Source  : 68,335,051
        Current Archive : 10,952,043

    ## Test Configuration :

        $result = $archiver
                ->backupFrom('system_queue_job')
                ->primaryKey('id')
                ->whereClause("DATE(created_at) = '2023-08-04'")
                ->mode('BP')
                ->chunk('50000')
                ->onDebug()
                ->run();

    ## Test Data :
        2023-08-04      132488

    ## Expected Result :-
        Backup : 132,488
        Purge : 132,488

    ## Actual Result :-

        {
            "status": "completed",
            "mode": "BP",
            "table": "system_queue_job",
            "backup_table": "system_queue_job_ARC",
            "total": 132488,
            "processed": {
                "backup": 132488,
                "purge": 132488
            },
            "messages": "Records processed successfully",
            "execution_time": "00:04:37.237",
            "threads": 1,
            "memory": {
                "initial": "10 MB",
                "final": "10 MB",
                "peak": "10 MB",
                "used": "0 B"
            }
        }
    
    ## Verify Data After Running Test :
        Current Source  : 68,202,563
        Current Archive : 11,084,531

    ## Log File : 2025-02-21_TC04.log

    ## Result : PASSED ✅

====================================================================================================