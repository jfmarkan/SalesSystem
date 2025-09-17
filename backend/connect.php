<?php
$serverName = "ZAT10084";
$connectionOptions = [
    "Database" => "STBSPOT",
    "Uid" => "steinbacherSalesSystem",
    "PWD" => "qbpsoCKMBx1QvIHUdFczWfSw",
];

// Conexión
$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn) {
    echo "✅ Conexión exitosa a SQL Server";
    sqlsrv_close($conn);
} else {
    echo "❌ Error de conexión:<br>";
    print_r(sqlsrv_errors());
}