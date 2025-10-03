<?php
class Compra {
    private mysqli $db;
    public function __construct(mysqli $db){ $this->db = $db; }

    /* === Datos base === */
    public function proveedores(): mysqli_result {
        return $this->db->query("SELECT id, razonSocial FROM Proveedor ORDER BY razonSocial");
    }

    // Productos (con categoría, stock) y si hay proveedor seleccionado, prioriza los asociados
    public function productos(?int $proveedor_id=null): mysqli_result {
        if ($proveedor_id) {
            $sql = "SELECT p.id, p.nombre, p.precio, p.stock_actual, p.stock_minimo,
                           c.nombre AS categoria, 1 AS asociado
                    FROM Producto p
                    LEFT JOIN Categoria c ON c.id=p.categoria_id
                    INNER JOIN ProductoProveedor pp ON pp.producto_id=p.id
                    WHERE pp.proveedor_id=$proveedor_id
                    UNION ALL
                    SELECT p.id, p.nombre, p.precio, p.stock_actual, p.stock_minimo,
                           c.nombre AS categoria, 0 AS asociado
                    FROM Producto p
                    LEFT JOIN Categoria c ON c.id=p.categoria_id
                    WHERE p.id NOT IN (SELECT producto_id FROM ProductoProveedor WHERE proveedor_id=$proveedor_id)
                    ORDER BY asociado DESC, nombre ASC";
        } else {
            $sql = "SELECT p.id, p.nombre, p.precio, p.stock_actual, p.stock_minimo,
                           c.nombre AS categoria, 0 AS asociado
                    FROM Producto p
                    LEFT JOIN Categoria c ON c.id=p.categoria_id
                    ORDER BY p.nombre ASC";
        }
        return $this->db->query($sql);
    }

    /* === RF04.03: Sugerencias === */
    public function sugerencias(int $days=30, int $limit=20): array {
        $days = max(7, min(90,$days));
        $sql = "
        WITH rotacion AS (
            SELECT d.producto_id, SUM(d.cantidad) cant
            FROM DetalleVenta d
            INNER JOIN Venta v ON v.id=d.venta_id
            WHERE v.fecha >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
            GROUP BY d.producto_id
        )
        SELECT p.id, p.nombre, p.stock_actual, p.stock_minimo, IFNULL(r.cant,0) vendidos_{$days}
        FROM Producto p
        LEFT JOIN rotacion r ON r.producto_id=p.id
        WHERE p.stock_actual <= p.stock_minimo OR IFNULL(r.cant,0) > 0
        ORDER BY (p.stock_actual - p.stock_minimo) ASC, IFNULL(r.cant,0) DESC
        LIMIT $limit";
        $r = $this->db->query($sql);
        return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
    }

    /* === RF04.04: Top vendidos === */
    public function topVendidos(int $days=30, int $limit=10): array {
        $days = max(7, min(365, $days));
        $sql = "SELECT p.id, p.nombre, SUM(d.cantidad) cant, SUM(d.subtotal) importe
                FROM DetalleVenta d
                INNER JOIN Venta v ON v.id=d.venta_id
                INNER JOIN Producto p ON p.id=d.producto_id
                WHERE v.fecha >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
                GROUP BY p.id, p.nombre
                ORDER BY cant DESC
                LIMIT $limit";
        $r = $this->db->query($sql);
        return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
    }

    /* === RF04.01: Registrar compra === */
    public function registrarCompra(int $proveedor_id, int $usuario_id, array $items, string $obs=''): int {
        $this->db->begin_transaction();
        try {
            $hoy = (new DateTime())->format('Y-m-d');
            $st = $this->db->prepare("INSERT INTO OrdenCompra(proveedor_id, fecha, total, estado, observaciones) VALUES(?, ?, 0, 'Recibida', ?)");
            $st->bind_param("iss", $proveedor_id, $hoy, $obs);
            $st->execute();
            $orden_id = $st->insert_id;

            $total = 0.0;

            foreach($items as $it){
                $pid   = (int)($it['id'] ?? 0);
                $cant  = max(1, (int)($it['cant'] ?? 0));
                $costo = (float)($it['costo'] ?? 0);
                $lote  = trim($it['lote'] ?? '');
                $vto   = trim($it['vto']  ?? '');

                if($pid<=0 || $cant<=0 || $costo<=0) continue;

                $sub = $cant * $costo;
                $total += $sub;

                // Detalle orden de compra
                $stD = $this->db->prepare("INSERT INTO DetalleOrdenCompra(orden_id,producto_id,cantidad,precio_unitario,subtotal)
                                           VALUES(?,?,?,?,?)");
                $stD->bind_param("iiidd", $orden_id, $pid, $cant, $costo, $sub);
                $stD->execute();

                // Actualiza stock producto
                $this->db->query("UPDATE Producto SET stock_actual = stock_actual + {$cant} WHERE id={$pid}");

                // Historial
                $tipo="Compra"; $det = "OC #$orden_id";
                $stH = $this->db->prepare("INSERT INTO HistorialStock(producto_id,tipo,cantidad,detalle) VALUES(?,?,?,?)");
                $stH->bind_param("isis",$pid,$tipo,$cant,$det);
                $stH->execute();

                // Lote (opcional)
                if($lote !== '' || $vto !== ''){
                    $stL = $this->db->prepare("INSERT INTO Lote(producto_id,numero_lote,fecha_vencimiento,cantidad_inicial,cantidad_actual)
                                               VALUES(?,?,?,?,?)");
                    $stL->bind_param("issii", $pid, $lote, $vto, $cant, $cant);
                    $stL->execute();
                }

                // RF04.02: asociar proveedor
                $this->db->query("INSERT IGNORE INTO ProductoProveedor(producto_id, proveedor_id, precio_ultimo)
                                  VALUES ($pid, $proveedor_id, $costo)");
                $this->db->query("UPDATE ProductoProveedor SET precio_ultimo=$costo
                                  WHERE producto_id=$pid AND proveedor_id=$proveedor_id");
            }

            // total en OC
            $stU = $this->db->prepare("UPDATE OrdenCompra SET total=? WHERE id=?");
            $stU->bind_param("di", $total, $orden_id);
            $stU->execute();

            $this->db->commit();
            return $orden_id;
        } catch(Exception $e){
            $this->db->rollback();
            throw $e;
        }
    }

    /* === NUEVO: Catálogo detallado por proveedor === */
    public function catalogoPorProveedor(int $proveedor_id): mysqli_result {
        $sql = "SELECT p.id, p.nombre, p.presentacion, p.precio, 
                       p.stock_actual, p.stock_minimo,
                       c.nombre AS categoria, 
                       l.numero_lote, l.fecha_vencimiento,
                       IF(pp.proveedor_id IS NULL,0,1) AS asociado
                FROM Producto p
                LEFT JOIN Categoria c ON c.id=p.categoria_id
                LEFT JOIN ProductoProveedor pp 
                       ON pp.producto_id=p.id AND pp.proveedor_id=?
                LEFT JOIN Lote l ON l.producto_id=p.id
                ORDER BY c.nombre, p.nombre ASC";
        $st = $this->db->prepare($sql);
        $st->bind_param("i",$proveedor_id);
        $st->execute();
        return $st->get_result();
    }
}
