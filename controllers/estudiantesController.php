<?php
require_once '../models/estudiantes.php';
require_once '../vendor/autoload.php';

use chillerlan\QRCode\{QRCode, QROptions};
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\Output\QRGdImagePNG; 

$option = isset($_GET['option']) ? $_GET['option'] : '';
$estudiantes = new EstudiantesModel();

header('Content-Type: application/json');

switch ($option) {
    case 'listar':
        $data = $estudiantes->getEstudiantes();
        
        // Verificar si hay estudiantes en la base de datos
        if ($data) {
            for ($i = 0; $i < count($data); $i++) {
                // Formatear el nombre completo
                $data[$i]['nombres'] = $data[$i]['nombre'] . ' ' . $data[$i]['apellido'];
                
                // Crear el badge para la carrera
                $data[$i]['carreras'] = '<span class="badge mx-1" style="background: #' . substr(md5($data[$i]['id_carrera']), 0, 6) . ';">' . $data[$i]['carrera'] . '</span>';
                
                // Crear el badge para el nivel
                $data[$i]['niveles'] = '<span class="badge mx-1" style="background: #' . substr(md5($data[$i]['id_nivel']), 0, 6) . ';">' . $data[$i]['nivel'] . '</span>';
        
                // Verificar si la imagen QR existe y devolverla
                $data[$i]['qr_image'] = file_exists('../' . $data[$i]['qr_image']) ? 
                    '<img src="../' . $data[$i]['qr_image'] . '" width="50">' : 
                    '<img src="../assets/images/default.gif" width="50">';
        
                // Acciones para editar o eliminar
                $data[$i]['accion'] = '<div class="d-flex">
                    <a class="btn btn-danger btn-sm" onclick="deleteEst(' . $data[$i]['id'] . ')"><i class="fas fa-eraser"></i></a>
                    <a class="btn btn-primary btn-sm" onclick="editEst(' . $data[$i]['id'] . ')"><i class="fas fa-edit"></i></a>
                </div>';
            }
            // Devolver los datos como JSON
            echo json_encode($data);
        } else {
            // Si no hay datos, devolver un array vacío
            echo json_encode([]);
        }
        break;


    case 'save':
        $codigo = $_POST['codigo'] ?? '';
        $nombre = $_POST['nombre'] ?? '';
        $apellido = $_POST['apellido'] ?? '';
        $telefono = $_POST['telefono'] ?? '';
        $direccion = $_POST['direccion'] ?? '';
        $carrera = $_POST['carrera'] ?? '';
        $nivel = $_POST['nivel'] ?? '';
        $id_estudiante = $_POST['id_estudiante'] ?? '';

        if (empty($codigo) || empty($nombre) || empty($apellido) || empty($telefono) || empty($direccion) || empty($carrera) || empty($nivel)) {
            echo json_encode(['tipo' => 'error', 'mensaje' => 'TODOS LOS CAMPOS SON REQUERIDOS']);
            exit;
        }

        // Generar el QR
        $qrDir = '../assets/images/qr/';
        $qrFile = $qrDir . md5($codigo) . '.png';

        if (!file_exists($qrDir)) {
            mkdir($qrDir, 0777, true);
        }

        // Crear QR
        $options = new QROptions();
        $options->version             = 5; // Versión del QR
        $options->outputInterface     = QRGdImagePNG::class; // Usa la salida de GD
        $options->scale               = 10; // Escala del QR
        $options->outputBase64        = false; // Si se desea base64
        $options->bgColor             = [255, 255, 255]; // Color de fondo blanco
        $options->moduleValues        = [
            QRMatrix::M_DATA_DARK => [0, 0, 0], // Módulo oscuro
            QRMatrix::M_DATA      => [255, 255, 255], // Módulo claro
        ];

        // Genera el QR y guarda la imagen
        $qrcode = new QRCode($options);
        $qrcode->render($codigo, $qrFile); // Guardar QR en la carpeta

        // Ahora que tenemos el QR, guardamos en la base de datos
        if (empty($id_estudiante)) {
            $consult = $estudiantes->comprobarCodigo($codigo, 0);
            if (empty($consult)) {
                $result = $estudiantes->save($codigo, $nombre, $apellido, $telefono, $direccion, $carrera, $nivel, $qrFile);
                $res = $result ? ['tipo' => 'success', 'mensaje' => 'EMPLEADO REGISTRADO', 'qr_url' => $qrFile] : ['tipo' => 'error', 'mensaje' => 'ERROR AL AGREGAR'];
            } else {
                $res = ['tipo' => 'error', 'mensaje' => 'EL CÓDIGO YA EXISTE'];
            }
        } else {
            $consult = $estudiantes->comprobarCodigo($codigo, $id_estudiante);
            if (empty($consult)) {
                $result = $estudiantes->update($codigo, $nombre, $apellido, $telefono, $direccion, $carrera, $nivel, $id_estudiante, $qrFile);
                $res = $result ? ['tipo' => 'success', 'mensaje' => 'EMPLEADO MODIFICADO', 'qr_url' => $qrFile] : ['tipo' => 'error', 'mensaje' => 'ERROR AL MODIFICAR'];
            } else {
                $res = ['tipo' => 'error', 'mensaje' => 'EL CÓDIGO YA EXISTE'];
            }
        }
        echo json_encode($res);
        break;

    case 'delete':
        $id = $_GET['id'] ?? '';
        if ($id) {
            $data = $estudiantes->delete($id);
            $res = $data ? ['tipo' => 'success', 'mensaje' => 'EMPLEADO ELIMINADO'] : ['tipo' => 'error', 'mensaje' => 'ERROR AL ELIMINAR'];
            echo json_encode($res);
        } else {
            echo json_encode(['tipo' => 'error', 'mensaje' => 'ID NO ESPECIFICADO']);
        }
        break;

    case 'edit':
        $id = $_GET['id'] ?? '';
        if ($id) {
            $data = $estudiantes->getEstudiante($id);
            echo json_encode($data);
        } else {
            echo json_encode(['tipo' => 'error', 'mensaje' => 'ID NO ESPECIFICADO']);
        }
        break;

    case 'datos':
        $item = $_GET['item'] ?? '';
        $data = $estudiantes->getDatos($item);
        echo json_encode($data);
        break;

    default:
        echo json_encode(['tipo' => 'error', 'mensaje' => 'OPCIÓN INVÁLIDA']);
        break;
}
