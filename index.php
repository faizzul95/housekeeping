<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bootstrap 5 DataTable</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
</head>
<body>
    <div class="container mt-5">
        <h2>Server-Side DataTable</h2>
        <table id="queueTable" class="table table-striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Count</th>
                    <th>Table</th>
                    <th>Action</th>
                </tr>
            </thead>
        </table>
    </div>

    <script>
        $(document).ready(function() {
            // $('#queueTable').DataTable({
            //     "processing": true,
            //     "serverSide": true,
            //     "ajax": {
            //         "url": "api.php",
            //         "type": "POST",
            //         "data": function(d) {
            //             d.action = 1;
            //         }
            //     },
            //     "columns": [
            //         { "data": "date" },
            //         { "data": "count" },
            //         { "data": "table" },
            //         { "data": "action" }
            //     ]
            // });
        });
    </script>
</body>
</html>