<?php

/**
 * Clase PagoServicio — Registro de pagos en la tabla PAGO_SERVICIO.
 */
class PagoServicio
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Registra un pago de servicio y devuelve el ID insertado.
     */
    public function registrar(int $cuentaId, float $monto, string $tipoServicio, string $fecha): int
    {
        $sql  = "INSERT INTO PAGO_SERVICIO (CUENTA_BANCARIA_idCUENTA_BANCARIA, PAGO_SERVICIO_monto, PAGO_SERVICIO_tipo_de_servicio, PAGO_SERVICIO_fecha) VALUES (?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("idss", $cuentaId, $monto, $tipoServicio, $fecha);
        $stmt->execute();
        $id = (int)$stmt->insert_id;
        $stmt->close();
        return $id;
    }
}
