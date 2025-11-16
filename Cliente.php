<?php
class Cliente {
    private mysqli $db;

    public function __construct(mysqli $db) {
        $this->db = $db;
    }

    public function listar(): mysqli_result {
    return $this->db->query("SELECT * FROM Cliente ORDER BY id ASC");
}


    public function obtener(int $id): ?array {
        $res = $this->db->query("SELECT * FROM Cliente WHERE id = $id");
        return $res->fetch_assoc() ?: null;
    }

    public function guardar(?int $id, array $data): bool {
        if ($id) {
            $sql = "UPDATE Cliente SET nombre=?, apellido=?, tipoDocumento=?, nroDocumento=?, telefono=?, email=?, direccion=? WHERE id=?";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("sssssssi",
                $data['nombre'], $data['apellido'], $data['tipoDocumento'],
                $data['nroDocumento'], $data['telefono'], $data['email'],
                $data['direccion'], $id
            );
        } else {
            $sql = "INSERT INTO Cliente (nombre, apellido, tipoDocumento, nroDocumento, telefono, email, direccion)
                    VALUES (?,?,?,?,?,?,?)";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param("sssssss",
                $data['nombre'], $data['apellido'], $data['tipoDocumento'],
                $data['nroDocumento'], $data['telefono'], $data['email'],
                $data['direccion']
            );
        }
        return $stmt->execute();
    }

    public function eliminar(int $id): bool {
        return $this->db->query("DELETE FROM Cliente WHERE id=$id");
    }
}
?>
