<?php

/**
 * Clase CuentaBancaria — Operaciones sobre la tabla CUENTA_BANCARIA.
 */
class CuentaBancaria
{
    private mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Devuelve la cuenta activa del usuario o null si no tiene.
     */
    public function obtenerCuentaActiva(int $usuarioId): ?array
    {
        $sql = "
            SELECT
                CUENTA_BANCARIA_numero_de_cuenta,
                CUENTA_BANCARIA_cbu,
                CUENTA_BANCARIA_saldo,
                CUENTA_BANCARIA_estado,
                CUENTA_BANCARIA_tipo_de_cuenta,
                CUENTA_BANCARIA_alias
            FROM CUENTA_BANCARIA
            WHERE USUARIO_idUSUARIO = ?
              AND CUENTA_BANCARIA_estado = 'Activa'
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $fila = $resultado->fetch_assoc();
        $stmt->close();
        return $fila;
    }

    /**
     * Devuelve todas las cuentas activas del usuario como resultado mysqli.
     */
    public function obtenerCuentasActivasResultado(int $usuarioId): mysqli_result
    {
        $sql  = "SELECT idCUENTA_BANCARIA, CUENTA_BANCARIA_numero_de_cuenta, CUENTA_BANCARIA_saldo FROM CUENTA_BANCARIA WHERE USUARIO_idUSUARIO = ? AND CUENTA_BANCARIA_estado = 'Activa'";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $usuarioId);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $stmt->close();
        return $resultado;
    }

    /**
     * Busca una cuenta por número, CBU o alias.
     * Devuelve el array de la cuenta o null.
     */
    public function buscarPorNumeroOCbuOAlias(string $destino): ?array
    {
        $sql = "
            SELECT
                CUENTA_BANCARIA_numero_de_cuenta,
                CUENTA_BANCARIA_cbu,
                CUENTA_BANCARIA_alias,
                CUENTA_BANCARIA_saldo,
                CUENTA_BANCARIA_estado,
                USUARIO_idUSUARIO
            FROM CUENTA_BANCARIA
            WHERE CUENTA_BANCARIA_numero_de_cuenta = ?
               OR CUENTA_BANCARIA_cbu = ?
               OR CUENTA_BANCARIA_alias = ?
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("sss", $destino, $destino, $destino);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $fila = $resultado->fetch_assoc();
        $stmt->close();
        return $fila;
    }

    /**
     * Obtiene el saldo actual de una cuenta por su número (con FOR UPDATE para transacciones).
     */
    public function obtenerSaldoParaActualizar(int $nroCuenta): ?float
    {
        $sql  = "SELECT CUENTA_BANCARIA_saldo FROM CUENTA_BANCARIA WHERE CUENTA_BANCARIA_numero_de_cuenta = ? FOR UPDATE";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $nroCuenta);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $fila = $resultado->fetch_assoc();
        $stmt->close();
        return $fila ? (float)$fila["CUENTA_BANCARIA_saldo"] : null;
    }

    /**
     * Obtiene saldo e ID de cuenta por su ID.
     */
    public function obtenerDatosPorId(int $cuentaId): ?array
    {
        $sql  = "SELECT CUENTA_BANCARIA_numero_de_cuenta, CUENTA_BANCARIA_saldo FROM CUENTA_BANCARIA WHERE idCUENTA_BANCARIA = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $cuentaId);
        $stmt->execute();
        $stmt->bind_result($numeroDeCuenta, $saldoActual);
        if (!$stmt->fetch()) {
            $stmt->close();
            return null;
        }
        $stmt->close();
        return ["numero_de_cuenta" => $numeroDeCuenta, "saldo" => (float)$saldoActual];
    }

    /**
     * Actualiza el saldo de una cuenta por número de cuenta.
     */
    public function actualizarSaldoPorNumero(float $nuevoSaldo, int $nroCuenta): void
    {
        $sql  = "UPDATE CUENTA_BANCARIA SET CUENTA_BANCARIA_saldo = ? WHERE CUENTA_BANCARIA_numero_de_cuenta = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("di", $nuevoSaldo, $nroCuenta);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Actualiza el saldo de una cuenta por su ID.
     */
    public function actualizarSaldoPorId(float $nuevoSaldo, int $cuentaId): void
    {
        $sql  = "UPDATE CUENTA_BANCARIA SET CUENTA_BANCARIA_saldo = ? WHERE idCUENTA_BANCARIA = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("di", $nuevoSaldo, $cuentaId);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Verifica si un número de cuenta está en uso.
     */
    public function numeroExiste(string $numero): bool
    {
        $sql  = "SELECT 1 FROM CUENTA_BANCARIA WHERE CUENTA_BANCARIA_numero_de_cuenta = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $numero);
        $stmt->execute();
        $stmt->store_result();
        $existe = $stmt->num_rows > 0;
        $stmt->close();
        return $existe;
    }

    /**
     * Verifica si un CBU está en uso.
     */
    public function cbuExiste(string $cbu): bool
    {
        $sql  = "SELECT 1 FROM CUENTA_BANCARIA WHERE CUENTA_BANCARIA_cbu = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $cbu);
        $stmt->execute();
        $stmt->store_result();
        $existe = $stmt->num_rows > 0;
        $stmt->close();
        return $existe;
    }

    /**
     * Verifica si un alias está en uso.
     */
    public function aliasExiste(string $alias): bool
    {
        $sql  = "SELECT 1 FROM CUENTA_BANCARIA WHERE CUENTA_BANCARIA_alias = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("s", $alias);
        $stmt->execute();
        $stmt->store_result();
        $existe = $stmt->num_rows > 0;
        $stmt->close();
        return $existe;
    }

    /**
     * Genera un número de cuenta único de 8 dígitos.
     */
    public function generarNumeroCuenta(): string
    {
        do {
            $numero = str_pad(mt_rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
        } while ($this->numeroExiste($numero));
        return $numero;
    }

    /**
     * Genera un CBU único de 10 dígitos que empieza con 100.
     */
    public function generarCBU(): string
    {
        do {
            $cbu = '100' . str_pad(mt_rand(0, 9999999), 7, '0', STR_PAD_LEFT);
        } while ($this->cbuExiste($cbu));
        return $cbu;
    }

    /**
     * Genera un alias único basado en nombre + apellido + número random.
     */
    public function generarAlias(string $nombre, string $apellido): string
    {
        $base = strtolower(trim($nombre)) . '.' . strtolower(trim($apellido));
        $base = preg_replace('/\s+/', '', $base);
        do {
            $alias = $base . mt_rand(10, 99);
        } while ($this->aliasExiste($alias));
        return $alias;
    }

    /**
     * Crea una nueva cuenta bancaria y devuelve su ID.
     */
    public function crear(string $numeroCuenta, string $cbu, string $alias, float $saldo, int $usuarioId, string $estado, string $tipoCuenta): int
    {
        $sql = "
            INSERT INTO CUENTA_BANCARIA
                (CUENTA_BANCARIA_numero_de_cuenta, CUENTA_BANCARIA_cbu, CUENTA_BANCARIA_alias,
                 CUENTA_BANCARIA_saldo, USUARIO_idUSUARIO, CUENTA_BANCARIA_estado, CUENTA_BANCARIA_tipo_de_cuenta)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("sssisss", $numeroCuenta, $cbu, $alias, $saldo, $usuarioId, $estado, $tipoCuenta);
        $stmt->execute();
        $id = (int)$stmt->insert_id;
        $stmt->close();
        return $id;
    }
}
