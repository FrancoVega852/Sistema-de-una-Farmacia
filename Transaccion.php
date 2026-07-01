<?php

/**
 * Clase Transaccion — Registro e historial de transacciones.
 */
class Transaccion
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Registra un ingreso de dinero (cajero automático).
     */
    public function registrarIngreso(int $nroCuentaDestino, float $monto, string $descripcion, string $moneda): void
    {
        $sql = "
            INSERT INTO TRANSACCIONES (
                TRANSACCIONES_cuenta_destino,
                TRANSACCIONES_monto,
                TRANSACCIONES_tipo_de_movimiento,
                TRANSACCIONES_descripcion,
                TRANSACCIONES_moneda,
                TRANSACCIONES_fecha_y_hora
            ) VALUES (?, ?, 'Ingreso', ?, ?, NOW())
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("idss", $nroCuentaDestino, $monto, $descripcion, $moneda);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Registra una transferencia (tanto el envío como la recepción en la misma tabla).
     * Devuelve el ID de la transacción insertada.
     */
    public function registrarTransferencia(
        float $monto,
        string $tipoMovimiento,
        string $descripcion,
        string $moneda,
        int $nroOrigen,
        int $nroDestino
    ): int {
        $sql = "
            INSERT INTO TRANSACCIONES
                (TRANSACCIONES_fecha_y_hora,
                 TRANSACCIONES_monto,
                 TRANSACCIONES_tipo_de_movimiento,
                 TRANSACCIONES_descripcion,
                 TRANSACCIONES_moneda,
                 TRANSACCIONES_cuenta_origen,
                 TRANSACCIONES_cuenta_destino)
            VALUES (NOW(), ?, ?, ?, ?, ?, ?)
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("dsssii", $monto, $tipoMovimiento, $descripcion, $moneda, $nroOrigen, $nroDestino);
        $stmt->execute();
        $id = (int)$stmt->insert_id;
        $stmt->close();
        return $id;
    }

    /**
     * Registra un pago de servicio.
     * Devuelve el ID de la transacción insertada.
     */
    public function registrarPagoServicio(
        float $monto,
        string $moneda,
        string $fecha,
        int $nroCuentaOrigen,
        string $descripcion,
        string $tipoMovimiento,
        string $estado
    ): int {
        $sql = "
            INSERT INTO TRANSACCIONES
                (TRANSACCIONES_monto, TRANSACCIONES_moneda, TRANSACCIONES_fecha_y_hora,
                 TRANSACCIONES_cuenta_origen, TRANSACCIONES_descripcion,
                 TRANSACCIONES_tipo_de_movimiento, TRANSACCIONES_estado)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("dssisss", $monto, $moneda, $fecha, $nroCuentaOrigen, $descripcion, $tipoMovimiento, $estado);
        $stmt->execute();
        $id = (int)$stmt->insert_id;
        $stmt->close();
        return $id;
    }

    /**
     * Vincula una transacción con una cuenta en la tabla CUENTA_BANCARIA_TRANSACCIONES.
     */
    public function vincularConCuenta(int $cuentaId, int $transaccionId): void
    {
        $sql  = "INSERT INTO CUENTA_BANCARIA_TRANSACCIONES (CUENTA_BANCARIA_idCUENTA_BANCARIA, TRANSACCIONES_idTRANSACCIONES) VALUES (?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ii", $cuentaId, $transaccionId);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Devuelve el resultado mysqli de las últimas transacciones de una cuenta (como origen o destino).
     */
    public function obtenerUltimasResultado(int $nroCuenta, int $limite = 10): mysqli_result
    {
        $sql = "
            SELECT
                TRANSACCIONES_fecha_y_hora,
                TRANSACCIONES_monto,
                TRANSACCIONES_tipo_de_movimiento,
                TRANSACCIONES_descripcion,
                TRANSACCIONES_moneda,
                TRANSACCIONES_cuenta_origen,
                TRANSACCIONES_cuenta_destino
            FROM TRANSACCIONES
            WHERE TRANSACCIONES_cuenta_origen = ?
               OR TRANSACCIONES_cuenta_destino = ?
            ORDER BY TRANSACCIONES_fecha_y_hora DESC
            LIMIT ?
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("iii", $nroCuenta, $nroCuenta, $limite);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $stmt->close();
        return $resultado;
    }
}
