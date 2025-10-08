<?php
/**
 * Repositorio para operaciones del módulo de mantenimiento de equipos
 */

require_once dirname(__DIR__, 3) . '/config/database.php';

class MantenimientoRepository
{
    private PDO $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    private function generarFolio(?string $fechaRecepcion = null): string
    {
        $fecha = $fechaRecepcion ? new DateTime($fechaRecepcion) : new DateTime();
        $anio = $fecha->format('Y');

        $query = "SELECT COUNT(*) AS total FROM mantenimientos WHERE YEAR(fecha_recepcion) = :anio";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':anio', $anio, PDO::PARAM_INT);
        $stmt->execute();
        $resultado = (int) $stmt->fetchColumn();

        $secuencial = $resultado + 1;
        return sprintf('MT-%s-%04d', $anio, $secuencial);
    }

    private function generarTokenPublico(): string
    {
        return bin2hex(random_bytes(12));
    }

    private function registrarBitacora(int $mantenimientoId, string $estado, int $usuarioId, ?string $comentario = null): void
    {
        $stmtHistorial = $this->conn->prepare(
            "INSERT INTO mantenimiento_estados_historial (mantenimiento_id, estado, comentario, usuario_id)
             VALUES (:mantenimiento_id, :estado, :comentario, :usuario_id)"
        );
        $stmtHistorial->execute([
            ':mantenimiento_id' => $mantenimientoId,
            ':estado' => $estado,
            ':comentario' => $comentario,
            ':usuario_id' => $usuarioId,
        ]);
    }

    private function asegurarEquipo(array $data): int
    {
        $stmt = $this->conn->prepare(
            "SELECT id FROM equipos WHERE codigo = :codigo OR serie = :serie LIMIT 1"
        );
        $stmt->execute([
            ':codigo' => $data['codigo'],
            ':serie' => $data['serie'],
        ]);
        $equipo = $stmt->fetch();

        if ($equipo) {
            // Actualizar datos básicos por si cambiaron
            $stmtUpdate = $this->conn->prepare(
                "UPDATE equipos SET descripcion = :descripcion, ubicacion = :ubicacion, usuario_referencia = :usuario
                 WHERE id = :id"
            );
            $stmtUpdate->execute([
                ':descripcion' => $data['descripcion'],
                ':ubicacion' => $data['ubicacion'],
                ':usuario' => $data['usuario'],
                ':id' => $equipo['id'],
            ]);
            return (int) $equipo['id'];
        }

        $stmtInsert = $this->conn->prepare(
            "INSERT INTO equipos (codigo, descripcion, serie, ubicacion, usuario_referencia)
             VALUES (:codigo, :descripcion, :serie, :ubicacion, :usuario)"
        );
        $stmtInsert->execute([
            ':codigo' => $data['codigo'],
            ':descripcion' => $data['descripcion'],
            ':serie' => $data['serie'],
            ':ubicacion' => $data['ubicacion'],
            ':usuario' => $data['usuario'],
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function registrarRecepcion(array $data): array
    {
        $this->conn->beginTransaction();
        try {
            $equipoId = $this->asegurarEquipo([
                'codigo' => $data['codigo_equipo'],
                'descripcion' => $data['descripcion_equipo'],
                'serie' => $data['serie_equipo'],
                'ubicacion' => $data['ubicacion'],
                'usuario' => $data['usuario_entrega'],
            ]);

            $folio = $this->generarFolio($data['fecha_recepcion'] ?? null);
            $token = $this->generarTokenPublico();

            $stmt = $this->conn->prepare(
                "INSERT INTO mantenimientos (
                    folio, public_token, equipo_id, tipo_mantenimiento, estado, recepcionista_id,
                    fecha_recepcion, observaciones_recepcion, usuario_entrega
                ) VALUES (
                    :folio, :token, :equipo_id, :tipo_mantenimiento, :estado, :recepcionista_id,
                    :fecha_recepcion, :observaciones, :usuario_entrega
                )"
            );
            $stmt->execute([
                ':folio' => $folio,
                ':token' => $token,
                ':equipo_id' => $equipoId,
                ':tipo_mantenimiento' => $data['tipo_mantenimiento'],
                ':estado' => 'en_recepcion',
                ':recepcionista_id' => $data['recepcionista_id'],
                ':fecha_recepcion' => $data['fecha_recepcion'],
                ':observaciones' => $data['observaciones_recepcion'],
                ':usuario_entrega' => $data['usuario_entrega'],
            ]);

            $mantenimientoId = (int) $this->conn->lastInsertId();
            $this->registrarBitacora($mantenimientoId, 'en_recepcion', $data['recepcionista_id'], 'Registro de recepción del equipo');

            $this->conn->commit();

            return [
                'id' => $mantenimientoId,
                'folio' => $folio,
                'token' => $token,
            ];
        } catch (Throwable $th) {
            $this->conn->rollBack();
            throw $th;
        }
    }

    public function asignarTecnico(int $mantenimientoId, int $tecnicoId, int $usuarioId): void
    {
        $stmt = $this->conn->prepare("UPDATE mantenimientos SET tecnico_id = :tecnico WHERE id = :id");
        $stmt->execute([
            ':tecnico' => $tecnicoId,
            ':id' => $mantenimientoId,
        ]);

        $this->registrarBitacora($mantenimientoId, 'en_diagnostico', $usuarioId, 'Técnico asignado');
        $stmtEstado = $this->conn->prepare("UPDATE mantenimientos SET estado = 'en_diagnostico' WHERE id = :id");
        $stmtEstado->execute([':id' => $mantenimientoId]);
    }

    public function registrarDiagnostico(int $mantenimientoId, array $data): void
    {
        $stmtExiste = $this->conn->prepare("SELECT id FROM diagnosticos WHERE mantenimiento_id = :id");
        $stmtExiste->execute([':id' => $mantenimientoId]);
        $diagnostico = $stmtExiste->fetch();

        if ($diagnostico) {
            $stmt = $this->conn->prepare(
                "UPDATE diagnosticos SET descripcion_falla = :descripcion, causa = :causa,
                    accion_recomendada = :accion, tecnico_id = :tecnico_id, fecha_diagnostico = :fecha,
                    aprobado_por = :aprobado_por, observaciones_supervisor = :observaciones
                 WHERE mantenimiento_id = :id"
            );
        } else {
            $stmt = $this->conn->prepare(
                "INSERT INTO diagnosticos (mantenimiento_id, tecnico_id, descripcion_falla, causa,
                    accion_recomendada, fecha_diagnostico, aprobado_por, observaciones_supervisor)
                 VALUES (:id, :tecnico_id, :descripcion, :causa, :accion, :fecha, :aprobado_por, :observaciones)"
            );
        }

        $stmt->execute([
            ':id' => $mantenimientoId,
            ':tecnico_id' => $data['tecnico_id'],
            ':descripcion' => $data['descripcion_falla'],
            ':causa' => $data['causa'],
            ':accion' => $data['accion_recomendada'],
            ':fecha' => $data['fecha_diagnostico'],
            ':aprobado_por' => $data['aprobado_por'],
            ':observaciones' => $data['observaciones_supervisor'],
        ]);

        if (!empty($data['aprobado_por'])) {
            $stmtSupervisor = $this->conn->prepare("UPDATE mantenimientos SET supervisor_id = :supervisor WHERE id = :id");
            $stmtSupervisor->execute([
                ':supervisor' => $data['aprobado_por'],
                ':id' => $mantenimientoId,
            ]);
        }

        $this->registrarBitacora($mantenimientoId, 'en_mantenimiento', $data['tecnico_id'], 'Diagnóstico registrado');
        $stmtEstado = $this->conn->prepare("UPDATE mantenimientos SET estado = 'en_mantenimiento' WHERE id = :id");
        $stmtEstado->execute([':id' => $mantenimientoId]);
    }

    public function registrarMantenimiento(int $mantenimientoId, array $data, array $repuestos, int $usuarioId): void
    {
        $this->conn->beginTransaction();
        try {
            $stmt = $this->conn->prepare(
                "UPDATE mantenimientos SET fecha_inicio = :inicio, fecha_fin = :fin, duracion_horas = :duracion,
                    observaciones_finales = :observaciones, estado = :estado
                 WHERE id = :id"
            );
            $stmt->execute([
                ':inicio' => $data['fecha_inicio'],
                ':fin' => $data['fecha_fin'],
                ':duracion' => $data['duracion'],
                ':observaciones' => $data['observaciones'],
                ':estado' => $data['estado'],
                ':id' => $mantenimientoId,
            ]);

            $stmtDelete = $this->conn->prepare("DELETE FROM mantenimiento_repuestos WHERE mantenimiento_id = :id");
            $stmtDelete->execute([':id' => $mantenimientoId]);

            if (!empty($repuestos)) {
                $stmtInsert = $this->conn->prepare(
                    "INSERT INTO mantenimiento_repuestos (mantenimiento_id, insumo_id, cantidad, observaciones)
                     VALUES (:mantenimiento_id, :insumo_id, :cantidad, :observaciones)"
                );
                foreach ($repuestos as $repuesto) {
                    $stmtInsert->execute([
                        ':mantenimiento_id' => $mantenimientoId,
                        ':insumo_id' => $repuesto['insumo_id'],
                        ':cantidad' => $repuesto['cantidad'],
                        ':observaciones' => $repuesto['observaciones'] ?? null,
                    ]);
                }
            }

            $this->sincronizarConocimientoBorrador($mantenimientoId, $repuestos, $usuarioId);

            $this->registrarBitacora($mantenimientoId, $data['estado'], $usuarioId, 'Ejecución del mantenimiento actualizada');
            $this->conn->commit();
        } catch (Throwable $th) {
            $this->conn->rollBack();
            throw $th;
        }
    }

    private function sincronizarConocimientoBorrador(int $mantenimientoId, array $repuestos, int $usuarioId): void
    {
        if (empty($repuestos)) {
            return;
        }

        $stmt = $this->conn->prepare(
            "SELECT conocimiento_id FROM mantenimiento_conocimientos WHERE mantenimiento_id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $mantenimientoId]);
        $enlace = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($enlace) {
            $conocimientoId = (int) $enlace['conocimiento_id'];
            $this->conn->prepare("UPDATE conocimientos SET estado = 'borrador' WHERE id = :id")
                ->execute([':id' => $conocimientoId]);
        } else {
            $conocimientoId = $this->crearConocimientoBorradorDesdeMantenimiento($mantenimientoId, $usuarioId);
            if (!$conocimientoId) {
                return;
            }

            $stmtEnlace = $this->conn->prepare(
                "INSERT INTO mantenimiento_conocimientos (mantenimiento_id, conocimiento_id, creado_por)
                 VALUES (:mantenimiento_id, :conocimiento_id, :creado_por)"
            );
            $stmtEnlace->execute([
                ':mantenimiento_id' => $mantenimientoId,
                ':conocimiento_id' => $conocimientoId,
                ':creado_por' => $usuarioId,
            ]);
        }

        if (empty($conocimientoId)) {
            return;
        }

        $stmtBorrarDetalle = $this->conn->prepare("DELETE FROM detalle_conocimientos WHERE conocimiento_id = :id");
        $stmtBorrarDetalle->execute([':id' => $conocimientoId]);

        $stmtInsertDetalle = $this->conn->prepare(
            "INSERT INTO detalle_conocimientos (conocimiento_id, insumo_id, cantidad, observaciones)
             VALUES (:conocimiento_id, :insumo_id, :cantidad, :observaciones)"
        );

        foreach ($repuestos as $repuesto) {
            $stmtInsertDetalle->execute([
                ':conocimiento_id' => $conocimientoId,
                ':insumo_id' => $repuesto['insumo_id'],
                ':cantidad' => $repuesto['cantidad'],
                ':observaciones' => $repuesto['observaciones'] ?? null,
            ]);
        }
    }

    private function crearConocimientoBorradorDesdeMantenimiento(int $mantenimientoId, int $usuarioId): ?int
    {
        $stmt = $this->conn->prepare(
            "SELECT m.*, e.descripcion AS equipo_descripcion, e.ubicacion
             FROM mantenimientos m
             INNER JOIN equipos e ON m.equipo_id = e.id
             WHERE m.id = :id"
        );
        $stmt->execute([':id' => $mantenimientoId]);
        $mantenimiento = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$mantenimiento) {
            return null;
        }

        $entreganteId = $mantenimiento['tecnico_id'] ?: $usuarioId;
        if (empty($entreganteId)) {
            $entreganteId = $mantenimiento['recepcionista_id'];
        }
        if (empty($entreganteId)) {
            return null;
        }

        $receptorId = $this->resolverReceptorParaMantenimiento($mantenimiento['usuario_entrega'] ?? '');
        if (empty($receptorId)) {
            return null;
        }

        $fechaEntrega = $mantenimiento['fecha_fin'] ? substr($mantenimiento['fecha_fin'], 0, 10) : date('Y-m-d');
        $lugarEntrega = $mantenimiento['ubicacion'] ?: ('Mantenimiento ' . $mantenimiento['folio']);
        $observaciones = sprintf(
            'Conocimiento generado automáticamente desde el mantenimiento %s para documentar repuestos.',
            $mantenimiento['folio']
        );

        $stmtInsert = $this->conn->prepare(
            "INSERT INTO conocimientos (fecha_entrega, lugar_entrega, entregante_id, receptor_id, observaciones_generales, estado, creado_por)
             VALUES (:fecha_entrega, :lugar_entrega, :entregante_id, :receptor_id, :observaciones, 'borrador', :creado_por)"
        );
        $stmtInsert->execute([
            ':fecha_entrega' => $fechaEntrega,
            ':lugar_entrega' => $lugarEntrega,
            ':entregante_id' => $entreganteId,
            ':receptor_id' => $receptorId,
            ':observaciones' => $observaciones,
            ':creado_por' => $usuarioId,
        ]);

        return (int) $this->conn->lastInsertId();
    }

    private function resolverReceptorParaMantenimiento(string $nombreReferencia): ?int
    {
        if ($nombreReferencia !== '') {
            $stmt = $this->conn->prepare(
                "SELECT id FROM receptores WHERE nombre_completo = :nombre LIMIT 1"
            );
            $stmt->execute([':nombre' => $nombreReferencia]);
            $receptor = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($receptor) {
                return (int) $receptor['id'];
            }
        }

        $stmt = $this->conn->prepare("SELECT id FROM receptores WHERE activo = 1 ORDER BY id ASC LIMIT 1");
        $stmt->execute();
        $receptor = $stmt->fetch(PDO::FETCH_ASSOC);

        return $receptor ? (int) $receptor['id'] : null;
    }

    public function registrarEntrega(int $mantenimientoId, array $data): void
    {
        $stmtExiste = $this->conn->prepare("SELECT id FROM entregas WHERE mantenimiento_id = :id");
        $stmtExiste->execute([':id' => $mantenimientoId]);
        $entrega = $stmtExiste->fetch();

        if ($entrega) {
            $stmt = $this->conn->prepare(
                "UPDATE entregas SET fecha_entrega = :fecha, observaciones = :observaciones, entregado_por = :entregado,
                    recibido_por = :recibido, qr_code_url = :qr
                 WHERE mantenimiento_id = :id"
            );
        } else {
            $stmt = $this->conn->prepare(
                "INSERT INTO entregas (mantenimiento_id, fecha_entrega, observaciones, entregado_por, recibido_por, qr_code_url)
                 VALUES (:id, :fecha, :observaciones, :entregado, :recibido, :qr)"
            );
        }

        $stmt->execute([
            ':id' => $mantenimientoId,
            ':fecha' => $data['fecha_entrega'],
            ':observaciones' => $data['observaciones_entrega'],
            ':entregado' => $data['entregado_por'],
            ':recibido' => $data['recibido_por'],
            ':qr' => $data['qr_code_url'],
        ]);

        $stmtEstado = $this->conn->prepare("UPDATE mantenimientos SET estado = :estado WHERE id = :id");
        $stmtEstado->execute([
            ':estado' => $data['estado'],
            ':id' => $mantenimientoId,
        ]);

        $this->registrarBitacora($mantenimientoId, $data['estado'], $data['entregado_por'], 'Entrega registrada');
    }

    public function registrarSeguimiento(int $mantenimientoId, array $data): void
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO seguimientos (mantenimiento_id, supervisor_id, descripcion, fecha_seguimiento, estado)
             VALUES (:id, :supervisor, :descripcion, :fecha, :estado)"
        );
        $stmt->execute([
            ':id' => $mantenimientoId,
            ':supervisor' => $data['supervisor_id'],
            ':descripcion' => $data['descripcion'],
            ':fecha' => $data['fecha'],
            ':estado' => $data['estado'],
        ]);

        $stmtActualizar = $this->conn->prepare("UPDATE mantenimientos SET estado = :estado WHERE id = :id");
        $stmtActualizar->execute([
            ':estado' => $data['estado_final'],
            ':id' => $mantenimientoId,
        ]);

        $this->registrarBitacora($mantenimientoId, $data['estado_final'], $data['supervisor_id'], 'Seguimiento registrado');
    }

    public function obtenerMantenimientoPorFolio(string $folio): ?array
    {
        $stmt = $this->conn->prepare(
            "SELECT m.*, e.codigo, e.descripcion AS equipo_descripcion, e.serie, e.ubicacion,
                    e.usuario_referencia, u.nombre_completo AS tecnico_nombre,
                    s.nombre_completo AS supervisor_nombre, r.nombre_completo AS recepcionista_nombre
             FROM mantenimientos m
             INNER JOIN equipos e ON m.equipo_id = e.id
             LEFT JOIN usuarios u ON m.tecnico_id = u.id
             LEFT JOIN usuarios s ON m.supervisor_id = s.id
             LEFT JOIN usuarios r ON m.recepcionista_id = r.id
             WHERE m.folio = :folio"
        );
        $stmt->execute([':folio' => $folio]);
        $mantenimiento = $stmt->fetch();

        return $mantenimiento ?: null;
    }

    public function obtenerMantenimientoPorToken(string $token): ?array
    {
        $stmt = $this->conn->prepare(
            "SELECT m.*, e.codigo, e.descripcion AS equipo_descripcion, e.serie, e.ubicacion,
                    e.usuario_referencia, u.nombre_completo AS tecnico_nombre,
                    s.nombre_completo AS supervisor_nombre, r.nombre_completo AS recepcionista_nombre
             FROM mantenimientos m
             INNER JOIN equipos e ON m.equipo_id = e.id
             LEFT JOIN usuarios u ON m.tecnico_id = u.id
             LEFT JOIN usuarios s ON m.supervisor_id = s.id
             LEFT JOIN usuarios r ON m.recepcionista_id = r.id
             WHERE m.public_token = :token"
        );
        $stmt->execute([':token' => $token]);
        $mantenimiento = $stmt->fetch();

        return $mantenimiento ?: null;
    }

    public function obtenerDiagnostico(int $mantenimientoId): ?array
    {
        $stmt = $this->conn->prepare("SELECT * FROM diagnosticos WHERE mantenimiento_id = :id");
        $stmt->execute([':id' => $mantenimientoId]);
        $diagnostico = $stmt->fetch();
        return $diagnostico ?: null;
    }

    public function obtenerRepuestos(int $mantenimientoId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT mr.*, i.nombre AS insumo_nombre, i.codigo AS insumo_codigo, i.unidad_medida
             FROM mantenimiento_repuestos mr
             INNER JOIN insumos i ON mr.insumo_id = i.id
             WHERE mr.mantenimiento_id = :id
             ORDER BY i.nombre"
        );
        $stmt->execute([':id' => $mantenimientoId]);
        return $stmt->fetchAll();
    }

    public function obtenerConocimientoAsociado(int $mantenimientoId): ?array
    {
        $stmt = $this->conn->prepare(
            "SELECT mc.conocimiento_id, c.numero_conocimiento, c.estado
             FROM mantenimiento_conocimientos mc
             INNER JOIN conocimientos c ON mc.conocimiento_id = c.id
             WHERE mc.mantenimiento_id = :id"
        );
        $stmt->execute([':id' => $mantenimientoId]);
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);

        return $registro ?: null;
    }

    public function obtenerSeguimientos(int $mantenimientoId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT s.*, u.nombre_completo AS supervisor_nombre
             FROM seguimientos s
             LEFT JOIN usuarios u ON s.supervisor_id = u.id
             WHERE s.mantenimiento_id = :id
             ORDER BY s.fecha_seguimiento DESC"
        );
        $stmt->execute([':id' => $mantenimientoId]);
        return $stmt->fetchAll();
    }

    public function obtenerHistorialEstados(int $mantenimientoId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT h.*, u.nombre_completo
             FROM mantenimiento_estados_historial h
             LEFT JOIN usuarios u ON h.usuario_id = u.id
             WHERE h.mantenimiento_id = :id
             ORDER BY h.fecha_registro ASC"
        );
        $stmt->execute([':id' => $mantenimientoId]);
        return $stmt->fetchAll();
    }

    public function buscarMantenimientos(array $filtros): array
    {
        $where = [];
        $params = [];

        if (!empty($filtros['estado'])) {
            $where[] = 'm.estado = :estado';
            $params[':estado'] = $filtros['estado'];
        }

        if (!empty($filtros['tipo_mantenimiento'])) {
            $where[] = 'm.tipo_mantenimiento = :tipo';
            $params[':tipo'] = $filtros['tipo_mantenimiento'];
        }

        if (!empty($filtros['tecnico_id'])) {
            $where[] = 'm.tecnico_id = :tecnico';
            $params[':tecnico'] = $filtros['tecnico_id'];
        }

        if (!empty($filtros['fecha_inicio'])) {
            $where[] = 'DATE(m.fecha_recepcion) >= :fecha_inicio';
            $params[':fecha_inicio'] = $filtros['fecha_inicio'];
        }

        if (!empty($filtros['fecha_fin'])) {
            $where[] = 'DATE(m.fecha_recepcion) <= :fecha_fin';
            $params[':fecha_fin'] = $filtros['fecha_fin'];
        }

        if (!empty($filtros['folio'])) {
            $where[] = 'm.folio = :folio';
            $params[':folio'] = $filtros['folio'];
        }

        $sql = "SELECT m.*, e.descripcion AS equipo_descripcion, e.codigo AS equipo_codigo,
                       u.nombre_completo AS tecnico_nombre,
                       s.nombre_completo AS supervisor_nombre,
                       (SELECT COUNT(*) FROM mantenimiento_repuestos mr WHERE mr.mantenimiento_id = m.id) AS total_repuestos,
                       (SELECT COALESCE(SUM(mr.cantidad), 0) FROM mantenimiento_repuestos mr WHERE mr.mantenimiento_id = m.id) AS total_unidades,
                       mc.conocimiento_id,
                       c.numero_conocimiento AS conocimiento_numero,
                       c.estado AS conocimiento_estado
                FROM mantenimientos m
                INNER JOIN equipos e ON m.equipo_id = e.id
                LEFT JOIN usuarios u ON m.tecnico_id = u.id
                LEFT JOIN usuarios s ON m.supervisor_id = s.id
                LEFT JOIN mantenimiento_conocimientos mc ON mc.mantenimiento_id = m.id
                LEFT JOIN conocimientos c ON mc.conocimiento_id = c.id";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY m.fecha_recepcion DESC';

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
