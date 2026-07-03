<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permisos.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!puedeModificar()) {
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'message' => 'No tienes permisos para buscar cajas.'
        ]);
        exit;
    }

    $q = trim($_GET['q'] ?? '');

    if ($q === '') {
        echo json_encode([
            'ok' => true,
            'data' => []
        ]);
        exit;
    }

    $sql = "
        SELECT 
            c.id,
            c.numero_caja,
            c.estado,
            c.observacion,
            u.codigo AS ubicacion_codigo,
            COUNT(p.id) AS total_productos
        FROM cajas c
        LEFT JOIN ubicaciones u ON u.id = c.ubicacion_id
        LEFT JOIN productos p 
            ON p.caja_id = c.id 
            AND p.estado IN ('Disponible', 'Alerta pendiente', 'Revisar')
        WHERE 
            c.estado IN ('Activa', 'Revisar')
            AND (
                c.numero_caja LIKE :q1
                OR u.codigo LIKE :q2
            )
        GROUP BY 
            c.id,
            c.numero_caja,
            c.estado,
            c.observacion,
            u.codigo
        ORDER BY 
            u.codigo ASC,
            c.numero_caja ASC
        LIMIT 50
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':q1' => '%' . $q . '%',
        ':q2' => '%' . $q . '%'
    ]);

    $cajas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok' => true,
        'data' => $cajas
    ]);
    exit;

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'message' => 'Error interno al buscar cajas.',
        'error' => $e->getMessage()
    ]);
    exit;
}