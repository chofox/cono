<?php

class Puesto {
    private $conn;
    private $table_name = "puestos";

    public $id;
    public $nombre;
    public $activo;
    public $fecha_creacion;
    public $fecha_actualizacion;

    public function __construct($db){
        $this->conn = $db;
    }

    // Método para obtener todos los puestos
    public function getAll(){
        $query = "SELECT id, nombre, activo, fecha_creacion, fecha_actualizacion FROM " . $this->table_name . " ORDER BY nombre";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Método para obtener un puesto por ID
    public function getById(){
        $query = "SELECT id, nombre, activo, fecha_creacion, fecha_actualizacion FROM " . $this->table_name . " WHERE id = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if($row) {
            $this->nombre = $row['nombre'];
            $this->activo = $row['activo'];
            $this->fecha_creacion = $row['fecha_creacion'];
            $this->fecha_actualizacion = $row['fecha_actualizacion'];
        }
        return $row;
    }

    // Método para crear un puesto
    public function create(){
        $query = "INSERT INTO " . $this->table_name . " (nombre) VALUES (:nombre)";
        $stmt = $this->conn->prepare($query);

        $this->nombre = htmlspecialchars(strip_tags($this->nombre));

        $stmt->bindParam(":nombre", $this->nombre);

        if($stmt->execute()){
            return true;
        }
        return false;
    }

    // Método para actualizar un puesto
    public function update(){
        $query = "UPDATE " . $this->table_name . " SET nombre = :nombre, activo = :activo WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        $this->nombre = htmlspecialchars(strip_tags($this->nombre));
        $this->activo = htmlspecialchars(strip_tags($this->activo));
        $this->id = htmlspecialchars(strip_tags($this->id));

        $stmt->bindParam(':nombre', $this->nombre);
        $stmt->bindParam(':activo', $this->activo);
        $stmt->bindParam(':id', $this->id);

        if($stmt->execute()){
            return true;
        }
        return false;
    }

    // Método para eliminar un puesto
    public function delete(){
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);

        $this->id = htmlspecialchars(strip_tags($this->id));

        $stmt->bindParam(1, $this->id);

        if($stmt->execute()){
            return true;
        }
        return false;
    }

    // Método para verificar si hay usuarios asociados a un puesto
    public function hasAssociatedUsers(){
        $query = "SELECT COUNT(*) FROM usuarios WHERE puesto_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }

    // Método para verificar si hay receptores asociados a un puesto
    public function hasAssociatedReceptores(){
        $query = "SELECT COUNT(*) FROM receptores WHERE puesto_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    }
}
?>