<?php
require_once 'Cliente.php';

class ControladorClientes {
    private Cliente $cliente;

    public function __construct(mysqli $db) {
        $this->cliente = new Cliente($db);
    }

    public function listar() {
        return $this->cliente->listar();
    }

    public function obtener(int $id) {
        return $this->cliente->obtener($id);
    }

    public function guardar(?int $id, array $data) {
        return $this->cliente->guardar($id, $data);
    }

    public function eliminar(int $id) {
        return $this->cliente->eliminar($id);
    }
}
?>
