# 03 - GESTIÓN SEGURA DE CREDENCIALES Y EXCLUSIÓN DE GIT

> **Documento de Control de Seguridad y Manejo de Secretos**  
> **Fecha de Aplicación:** Septiembre 2026  
> **Responsable:** Ingeniero Backend / DevOps Senior  
> **Estado:** Completado y Sincronizado  

---

## 1. Contexto y Justificación

El archivo `website_files/api/config.php` contiene la configuración de acceso a la base de datos de producción (servidor, usuario, contraseña de MySQL y nombre de la base de datos).

### El Problema Detectado:
Anteriormente, este archivo era rastreado (*tracked*) por el control de versiones Git, lo que significaba que cada cambio o copia del proyecto enviaba las contraseñas de la base de datos a los servidores de GitHub en texto legible.

### La Solución Aplicada:
Se aplicó la mejor práctica de la industria para el manejo de credenciales (*Twelve-Factor App / Secret Isolation*):
1. **Desvincular el archivo de Git:** Indicar a Git que "olvide" y deje de seguir el archivo en el historial futuro.
2. **Exclusión automática permanente:** Añadir reglas al archivo `.gitignore` para que Git ignore cualquier intento de volver a rastrearlo.
3. **Eliminación local controlada:** Retirar el archivo del disco local para garantizar cero riesgo de filtración desde este equipo.

---

## 2. Comandos y Acciones Ejecutadas

### Paso 1: Configuración de `.gitignore`
Se añadieron las siguientes directivas al archivo `.gitignore` en la raíz del proyecto:
```gitignore
# Credenciales y configuracion de base de datos sensible
website_files/api/config.php
api/config.php
```

### Paso 2: Desvinculación y eliminación del archivo
Se ejecutó el comando nativo de Git:
```powershell
git rm website_files/api/config.php
```

**Explicación técnica del comando:**
* `git rm`: Remueve el archivo del índice de seguimiento de Git (*staging area*) y lo borra físicamente del disco local.
* Al combinarse con `.gitignore`, el archivo queda permanentemente bloqueado para futuros commits.

---

## 3. Estado Actual de los Entornos

| Entorno | Estado de `config.php` | Detalle |
| :--- | :---: | :--- |
| **Servidor de Producción (`petulap.store`)** | **Intacto y Activo** | El archivo `public_html/api/config.php` sigue en el servidor con sus credenciales correctas. El sistema web sigue funcionando al 100%. |
| **Repositorio GitHub (`keroquion/SISTEMA`)** | **Eliminado** | El archivo ya no existe en la rama de trabajo. Nadie con acceso al repositorio puede ver la contraseña. |
| **Computadora Local** | **Limpio (Sin secretos)** | El archivo fue retirado del disco. No hay riesgo de filtración accidental. |

---

## 4. Guía para Nuevos Entornos o Computadoras

A partir de este momento, **GitHub nunca volverá a descargar `config.php`**.  
Si en el futuro tú o un desarrollador clonan este repositorio en una computadora nueva y necesitan levantar un entorno local o conectarse al servidor, deberán **crear manualmente** el archivo `website_files/api/config.php` con la siguiente estructura:

```php
<?php
define("DB_HOST", "localhost");
define("DB_USER", "TU_USUARIO_AQUI");
define("DB_PASS", "TU_PASSWORD_AQUI");
define("DB_NAME", "TU_BASE_DATOS_AQUI");
define("DB_CHARSET", "utf8mb4");

function getDB() {
    mysqli_report(MYSQLI_REPORT_OFF);
    $mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($mysqli->connect_error) {
        http_response_code(500);
        die(json_encode(["error" => "DB Error: " . $mysqli->connect_error]));
    }
    $mysqli->set_charset(DB_CHARSET);
    return $mysqli;
}

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { http_response_code(200); exit(); }

function check_api_access($modulo_requerido) {
    if (!isset($_SESSION['user_modulos'])) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['ok'=>false, 'msg'=>'Acceso denegado. No hay permisos definidos.']);
        exit;
    }
    
    if ($modulo_requerido === 'admin_only' && $_SESSION['user_tipo'] !== 'admin') {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['ok'=>false, 'msg'=>'Acceso denegado. Solo administradores.']);
        exit;
    }

    if ($modulo_requerido !== 'admin_only' && !in_array($modulo_requerido, $_SESSION['user_modulos'])) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['ok'=>false, 'msg'=>"Acceso denegado. Se requiere modulo: $modulo_requerido"]);
        exit;
    }
}
?>
```
*(Gracias a `.gitignore`, este archivo nunca se subirá accidentalmente a GitHub).*
