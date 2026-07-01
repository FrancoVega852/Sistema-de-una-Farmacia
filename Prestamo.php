<?php

/**
 * Clase Prestamo — Operaciones sobre la tabla PRESTAMO.
 */
class Prestamo
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Solicita un nuevo préstamo y crea la notificación base "Solicitud registrada".
     * Devuelve un array ['ok' => bool, 'mensaje' => string, 'id' => int|null].
     */
    public function solicitar(
        int $usuarioId,
        int $cantidadCuotas,
        float $montoSolicitado,
        string $tipoInteres,
        Notificacion $notificacion
    ): array {
        if ($cantidadCuotas < 1 || $montoSolicitado <= 0 || $tipoInteres === '') {
            return [
                'ok'      => false,
                'mensaje' => "❌ Completá correctamente la cantidad de cuotas, el monto y el tipo de interés.",
                'id'      => null,
            ];
        }

        $montoAprobado   = $montoSolicitado * 0.96; // 4% de costos
        $estado          = 'Pendiente';
        $tipoMovimiento  = 'Crédito';

        $sql = "
            INSERT INTO PRESTAMO
                (PRESTAMO_cantidad_cuotas, PRESTAMO_estado, PRESTAMO_monto_solicitado,
                 PRESTAMO_monto_aprobado, PRESTAMO_tipo_de_interes,
                 PRESTAMO_tipo_de_movimiento_prestamo, USUARIO_idUSUARIO)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param(
            "isddssi",
            $cantidadCuotas,
            $estado,
            $montoSolicitado,
            $montoAprobado,
            $tipoInteres,
            $tipoMovimiento,
            $usuarioId
        );

        if (!$stmt->execute()) {
            $stmt->close();
            return [
                'ok'      => false,
                'mensaje' => "❌ Ocurrió un error al registrar el préstamo. Intentá nuevamente.",
                'id'      => null,
            ];
        }

        $idPrestamo   = (int)$this->db->insert_id;
        $stmt->close();

        // Notificación base
        $msgSolicitud = "[PRESTAMO:$idPrestamo] Solicitud registrada";
        if (!$notificacion->existe($usuarioId, $msgSolicitud)) {
            $notificacion->crear($usuarioId, $msgSolicitud, 'Préstamo', 'Pendiente');
        }

        $mensajeOk = "✅ Solicitud registrada. Monto solicitado: $" .
            number_format($montoSolicitado, 2, ',', '.') .
            " · Monto a acreditar (estimado): $" .
            number_format($montoAprobado, 2, ',', '.');

        return ['ok' => true, 'mensaje' => $mensajeOk, 'id' => $idPrestamo];
    }

    /**
     * Devuelve el resultado mysqli con todos los préstamos del usuario.
     */
    public function obtenerPorUsuarioResultado(int $usuarioId): mysqli_result
    {
        $sql = "
            SELECT
                idPRESTAMO,
                PRESTAMO_cantidad_cuotas,
                PRESTAMO_estado,
                PRESTAMO_monto_solicitado,
                PRESTAMO_monto_aprobado,
                PRESTAMO_tipo_de_interes,
                PRESTAMO_tipo_de_movimiento_prestamo
            FROM PRESTAMO
            WHERE USUARIO_idUSUARIO = ?
            ORDER BY idPRESTAMO DESC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $stmt->close();
        return $resultado;
    }

    /**
     * Busca la fecha de la notificación "Solicitud registrada" de un préstamo.
     */
    private function obtenerFechaSolicitud(int $usuarioId, int $idPrestamo): ?string
    {
        $mensaje = "[PRESTAMO:$idPrestamo] Solicitud registrada";
        $sql     = "
            SELECT NOTIFICACIONES_fecha_y_hora AS fecha
            FROM NOTIFICACIONES
            WHERE USUARIO_idUSUARIO = ?
              AND NOTIFICACIONES_tipo_de_notificaciones = 'Préstamo'
              AND NOTIFICACIONES_mensaje = ?
            ORDER BY NOTIFICACIONES_fecha_y_hora ASC
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("is", $usuarioId, $mensaje);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row['fecha'] ?? null;
    }

    /**
     * Auto-resuelve los préstamos pendientes de un usuario (se llama en cada carga de la página).
     * Lógica original conservada: monto <= $limiteAprobar → Aprobado, sino → Rechazado.
     * El estado cambia solo si pasaron $minutosEspera desde la solicitud.
     */
    public function autoResolver(int $usuarioId, Notificacion $notificacion, int $minutosEspera = 1, int $limiteAprobar = 200000): void
    {
        $sql  = "
            SELECT idPRESTAMO, PRESTAMO_monto_solicitado, PRESTAMO_monto_aprobado
            FROM PRESTAMO
            WHERE USUARIO_idUSUARIO = ?
              AND PRESTAMO_estado = 'Pendiente'
            ORDER BY idPRESTAMO ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();

        while ($p = $res->fetch_assoc()) {
            $idPrestamo = (int)$p['idPRESTAMO'];
            $montoSol   = (float)$p['PRESTAMO_monto_solicitado'];
            $montoApr   = (float)$p['PRESTAMO_monto_aprobado'];

            // Si no existe la notificación base, la creamos
            $msgSolicitud = "[PRESTAMO:$idPrestamo] Solicitud registrada";
            if (!$notificacion->existe($usuarioId, $msgSolicitud)) {
                $notificacion->crear($usuarioId, $msgSolicitud, 'Préstamo', 'Pendiente');
                continue; // recién creada → esperar al próximo refresh
            }

            $fechaSolicitud = $this->obtenerFechaSolicitud($usuarioId, $idPrestamo);
            if (!$fechaSolicitud) continue;

            $tsSolicitud = strtotime($fechaSolicitud);
            if ($tsSolicitud === false) continue;

            if ((time() - $tsSolicitud) < ($minutosEspera * 60)) {
                continue; // todavía no pasó el tiempo
            }

            // Regla simple de decisión
            $nuevoEstado = ($montoSol <= $limiteAprobar) ? 'Aprobado' : 'Rechazado';

            // Update seguro (solo si sigue Pendiente)
            $up = $this->db->prepare("
                UPDATE PRESTAMO
                SET PRESTAMO_estado = ?
                WHERE idPRESTAMO = ?
                  AND USUARIO_idUSUARIO = ?
                  AND PRESTAMO_estado = 'Pendiente'
            ");
            $up->bind_param("sii", $nuevoEstado, $idPrestamo, $usuarioId);
            $up->execute();
            $afectadas = $up->affected_rows;
            $up->close();

            if ($afectadas > 0) {
                if ($nuevoEstado === 'Aprobado') {
                    $msgFinal = "[PRESTAMO:$idPrestamo] ✅ APROBADO. Monto a acreditar: $" . number_format($montoApr, 2, ',', '.');
                } else {
                    $msgFinal = "[PRESTAMO:$idPrestamo] ❌ RECHAZADO. Podés intentar nuevamente con otro monto/cuotas.";
                }
                if (!$notificacion->existe($usuarioId, $msgFinal)) {
                    $notificacion->crear($usuarioId, $msgFinal, 'Préstamo', 'Pendiente');
                }
            }
        }
    }
}
