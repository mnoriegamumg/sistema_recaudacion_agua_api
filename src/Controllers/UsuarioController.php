<?php
namespace App\Controllers;

use App\Models\Usuario;

class UsuarioController {
    private Usuario $usuarioModel;

    public function __construct() {
        $this->usuarioModel = new Usuario();
    }

    /**
     * GET /api/usuarios
     * Listar todos los usuarios del sistema
     */
    public function listar() {
        $this->verificarAdmin();

        $usuarios = $this->usuarioModel->getAll();

        echo json_encode([
            'success' => true,
            'data' => $usuarios,
            'meta' => ['total' => count($usuarios)]
        ]);
    }

    /**
     * GET /api/usuarios/{id}
     * Obtener usuario por ID
     */
    public function obtener($id) {
        $this->verificarAdmin();

        $usuario = $this->usuarioModel->findById((int)$id);

        if (!$usuario) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Usuario no encontrado'
            ]);
            return;
        }

        echo json_encode([
            'success' => true,
            'data' => $usuario
        ]);
    }

    /**
     * POST /api/usuarios
     * Crear nuevo usuario
     */
    public function crear() {
        $this->verificarAdmin();

        $data = json_decode(file_get_contents('php://input'), true);

        // Validaciones
        if (empty($data['nombre_completo']) || empty($data['correo']) || empty($data['password'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'nombre_completo, correo y password son requeridos'
            ]);
            return;
        }

        if (!filter_var($data['correo'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'El correo no tiene un formato válido'
            ]);
            return;
        }

        if (strlen($data['password']) < 6) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'La contraseña debe tener al menos 6 caracteres'
            ]);
            return;
        }

        if ($this->usuarioModel->emailExists($data['correo'])) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'error' => 'El correo ya está registrado'
            ]);
            return;
        }

        $rolesPermitidos = ['admin', 'tesorero', 'cajero'];
        if (isset($data['rol']) && !in_array($data['rol'], $rolesPermitidos)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Rol inválido. Opciones: ' . implode(', ', $rolesPermitidos)
            ]);
            return;
        }

        $data['created_by'] = $_SERVER['USER_DATA']->sub ?? null;
        $usuario = $this->usuarioModel->create($data);

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'data' => $usuario,
            'message' => 'Usuario creado exitosamente'
        ]);
    }

    /**
     * PUT /api/usuarios/{id}
     * Actualizar usuario existente
     */
    public function actualizar($id) {
        $this->verificarAdmin();

        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)$id;

        $usuario = $this->usuarioModel->findById($id);
        if (!$usuario) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Usuario no encontrado'
            ]);
            return;
        }

        // Validar correo si se envía
        if (isset($data['correo'])) {
            if (!filter_var($data['correo'], FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'El correo no tiene un formato válido'
                ]);
                return;
            }

            if ($this->usuarioModel->emailExists($data['correo'], $id)) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'error' => 'El correo ya está en uso por otro usuario'
                ]);
                return;
            }
        }

        // Validar rol si se envía
        if (isset($data['rol'])) {
            $rolesPermitidos = ['admin', 'tesorero', 'cajero'];
            if (!in_array($data['rol'], $rolesPermitidos)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Rol inválido. Opciones: ' . implode(', ', $rolesPermitidos)
                ]);
                return;
            }
        }

        $data['updated_by'] = $_SERVER['USER_DATA']->sub ?? null;
        $result = $this->usuarioModel->update($id, $data);

        if (!$result) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'No se pudo actualizar el usuario'
            ]);
            return;
        }

        echo json_encode([
            'success' => true,
            'data' => $this->usuarioModel->findById($id),
            'message' => 'Usuario actualizado exitosamente'
        ]);
    }

    /**
     * PUT /api/usuarios/{id}/password
     * Cambiar contraseña de un usuario
     */
    public function cambiarPassword($id) {
        $this->verificarAdmin();

        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)$id;

        if (empty($data['password'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'La nueva contraseña es requerida'
            ]);
            return;
        }

        if (strlen($data['password']) < 6) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'La contraseña debe tener al menos 6 caracteres'
            ]);
            return;
        }

        $usuario = $this->usuarioModel->findById($id);
        if (!$usuario) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Usuario no encontrado'
            ]);
            return;
        }

        $updatedBy = $_SERVER['USER_DATA']->sub ?? null;
        $result = $this->usuarioModel->updatePassword($id, $data['password'], $updatedBy);

        if (!$result) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'No se pudo actualizar la contraseña'
            ]);
            return;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Contraseña actualizada exitosamente'
        ]);
    }

    /**
     * PUT /api/usuarios/perfil/password
     * Cambiar la propia contraseña (cualquier usuario autenticado)
     */
    public function cambiarMiPassword() {
        $userData = $_SERVER['USER_DATA'];
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['password_actual']) || empty($data['password_nueva'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'password_actual y password_nueva son requeridos'
            ]);
            return;
        }

        if (strlen($data['password_nueva']) < 6) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'La nueva contraseña debe tener al menos 6 caracteres'
            ]);
            return;
        }

        // Verificar contraseña actual
        if (!$this->usuarioModel->verifyPassword($userData->sub, $data['password_actual'])) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'La contraseña actual es incorrecta'
            ]);
            return;
        }

        $result = $this->usuarioModel->updatePassword(
            $userData->sub,
            $data['password_nueva'],
            $userData->sub
        );

        if (!$result) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'No se pudo actualizar la contraseña'
            ]);
            return;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Contraseña actualizada exitosamente'
        ]);
    }

    /**
     * PATCH /api/usuarios/{id}/toggle
     * Activar/Desactivar usuario
     */
    public function toggleActivo($id) {
        $this->verificarAdmin();

        $id = (int)$id;
        $usuario = $this->usuarioModel->findById($id);

        if (!$usuario) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Usuario no encontrado'
            ]);
            return;
        }

        $updatedBy = $_SERVER['USER_DATA']->sub ?? null;
        $this->usuarioModel->toggleActivo($id, $updatedBy);

        echo json_encode([
            'success' => true,
            'data' => $this->usuarioModel->findById($id),
            'message' => 'Estado del usuario actualizado'
        ]);
    }

    /**
     * Verificar que el usuario autenticado sea admin
     */
    private function verificarAdmin() {
        $userData = $_SERVER['USER_DATA'] ?? null;
        if (!$userData || $userData->rol !== 'admin') {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'No tienes permisos para realizar esta acción'
            ]);
            exit;
        }
    }
}