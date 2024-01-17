<?php

namespace App\Http\Modulos\DataInputWorker\Utils;

use App\Traits\DiTrait;
use Illuminate\Http\Request;
use openEtl\Tenant\Traits\TenantTrait;
use Illuminate\Support\Facades\Storage;
use App\Http\Modulos\configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class ProcesarArchivoPgp {
    private $request;
    private $baseDatos;
    private $nombreArchivo;
    private $columnas;
    private $passphrase = '0p3nt3cn0l0g1454';

    /**
     * ProcesarArchivoPgp constructor.
     *
     * @param \Illuminate\Http\Request $request
     * @param string $baseDatos Nombre de la base de datos del OFE
     * @param array $columnas Títulos de las columnas a ser insertadas como primera fila
     * @param $nombreRealArchivo
     */
    public function __construct(Request $request, $baseDatos, $columnas, $nombreRealArchivo) {
        $this->request = $request;
        $this->baseDatos = str_replace(config('variables_sistema.PREFIJO_BASE_DATOS'), 'etl_', $baseDatos);
        $this->columnas = $columnas;
        $this->nombreArchivo = $nombreRealArchivo;
    }

    /**
     * Procesa archivos PGP.
     *
     * @param $archivo
     * @return  array Tres índices relacionados con errores, nombre de archivo sanitizado y ruta al Excel generado
     */
    public function procesar($archivo) {
        DiTrait::setFilesystemsInfo();
        $nombreArchivoTxt = str_replace('pgp', 'txt', $this->nombreArchivo);
        $nombreArchivo = str_replace('.pgp', '', $this->nombreArchivo);
        // Real path del disco
        $path = Storage::disk(config('variables_sistema.ETL_ENCRIPTADOS'))->getDriver()->getAdapter()->getPathPrefix();
        $archivoTxt = $path . $this->baseDatos . '/' . $nombreArchivoTxt;
        $archivoRetorno = $path . $this->baseDatos . '/' . $nombreArchivo . '.csv';
        $this->nombreArchivo = TenantTrait::sanitizarCadena($this->nombreArchivo);

        @unlink($path . $this->baseDatos . "/" . $this->nombreArchivo);
        @unlink($archivoTxt);
        @unlink($archivoRetorno);
        // Guarda el archivo en disco
        Storage::disk(config('variables_sistema.ETL_ENCRIPTADOS'))->put($this->baseDatos . '/' . $this->nombreArchivo, file_get_contents($archivo));

        $error = '';
        try {
            // Ejecuta el comando de consola que permite desencriptar el archivo
            // exec("/usr/bin/gpg --pinentry-mode loopback --passphrase " . $this->passphrase . " --output " . $archivoTxt . " --decrypt " . $path . $this->baseDatos . "/" . $this->nombreArchivo);

            $a = [];
            $b = [];
            // Permite redireccionar el error a la salida estandar y visualizar donde falla la ejecucion del comando, que por lo general se debe a problemas de escritura
            // Por ejemplo:
            // gpg: fatal: can't create directory `/var/www/.gnupg': Permission denied
            // secmem usage: 64/64 bytes in 1/1 blocks of pool 64/65536
            $comando = "/usr/bin/gpg --pinentry-mode loopback --passphrase " . $this->passphrase . " --output " . $archivoTxt . " --decrypt " . $path . $this->baseDatos . "/" . $this->nombreArchivo . ' 2>&1';
            exec($comando, $a, $b);
            // dump(['salida' => $a, 'ejecucion_comando' => $b]);

            // Agrega la fila de columnas al archivo desencriptado
            $columnas = implode("\t", $this->columnas);
            shell_exec('sed -i \'1s;^;' . $columnas . ' \n;\' ' . $archivoTxt);
            // Convierte el archivo de texto en un archivo csv
            $lineas = [];
            $handle = fopen($archivoTxt, "r");
            if ($handle !== FALSE) {
                while (($data = fgetcsv($handle, 10000, "\t")) !== FALSE) {
                    $lineas[] = $data;
                }
                fclose($handle);
            }
            $handle = fopen($archivoRetorno, 'w');
            foreach ($lineas as $linea) {
                fputcsv($handle, $linea);
            }
            fclose($handle);
            // Elimina el archivo encriptado
            @unlink($path . $this->baseDatos . "/" . $this->nombreArchivo);
            
            // Elimina el archivo txt
            @unlink($archivoTxt);
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        // Se vuelve a llamar el método de carga inicial, pero ahora con el archivo de excel generado en disco
        return [
            'error'         => $error,
            'nombreArchivo' => $nombreArchivo,
            'archivo'       => $archivoRetorno
        ];
    }
}
