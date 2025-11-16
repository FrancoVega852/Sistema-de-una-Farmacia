<?php
require_once 'Compra.php';

class ControladorCompras {
    private Compra $compra;
    public function __construct(mysqli $db) { 
        $this->compra = new Compra($db); 
    }

    public function proveedores() { 
        return $this->compra->proveedores(); 
    }

    public function productos(?int $prov_id=null){ 
        return $this->compra->productos($prov_id); 
    }

    public function sugerencias(){ 
        return $this->compra->sugerencias(30, 20); 
    }

    public function topVendidos(){ 
        return $this->compra->topVendidos(30, 10); 
    }

    public function guardar(int $proveedor_id, int $usuario_id, array $items, string $obs=''){
        return $this->compra->registrarCompra($proveedor_id, $usuario_id, $items, $obs);
    }

    /** ✅ Nuevo método para catálogo por proveedor con categorías y lotes */
    public function catalogoPorProveedor(int $prov_id){
        return $this->compra->catalogoPorProveedor($prov_id);
    }
}
