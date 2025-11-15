<?php
require_once 'Reporte.php';

class ControladorReportes {
    private Reporte $reporte;

    public function __construct(mysqli $db) {
        $this->reporte = new Reporte($db);
    }

    public function ventas($periodo) { return $this->reporte->ventasPorPeriodo($periodo); }
    public function masVendidos() { return $this->reporte->productosMasVendidos(); }
    public function proximosVencer() { return $this->reporte->productosProximosAVencer(); }
    public function movimientosUsuario() { return $this->reporte->movimientosPorUsuario(); }
}
?>
