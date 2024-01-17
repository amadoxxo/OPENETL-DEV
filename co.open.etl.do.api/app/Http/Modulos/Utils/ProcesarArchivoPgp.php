<?php

namespace App\Http\Modulos\Utils;

use Illuminate\Http\Request;
use App\Http\Traits\MainTrait;
use Illuminate\Support\Facades\Storage;
use openETL\Main\Traits\PackageMainTrait;
use App\Http\Modulos\configuracion\ObligadosFacturarElectronicamente\ConfiguracionObligadoFacturarElectronicamente;

class ProcesarArchivoPgp {
    use PackageMainTrait;

    private $request;
    private $baseDatos;
    private $nombreArchivo;
    private $columnas;
    private $passphrase = '0p3nt3cn0l0g1454';

    /**
     * ProcesarArchivoPgp constructor.
     * 
     * @param  \Illuminate\Http\Request $request
     * @param  string $baseDatos Nombre de la base de datos del OFE
     * @param  array $columnas Títulos de las columnas a ser insertadas como primera fila
     */
    public function __construct(Request $request, $baseDatos, $columnas) {
        $this->request = $request;
        $this->baseDatos = $baseDatos;
        $this->columnas = $columnas;
        $this->nombreArchivo = $request->file('archivo')->getClientOriginalName();
    }

    /**
     * Procesa archivos PGP.
     * 
     * @return  array Tres índices relacionados con errores, nombre de archivo sanitizado y ruta al Excel generado 
     */
    public function procesar() {
        $this->nombreArchivo = $this->sanear_string($this->nombreArchivo);
        $nombreArchivoTxt = str_replace('pgp', 'txt', $this->nombreArchivo);
        $nombreArchivo = str_replace('.pgp', '', $this->nombreArchivo);

        // Guarda el archivo en disco
        $this->request->file('archivo')->storeAs(
            $this->baseDatos, // Path
            $this->nombreArchivo, // Nombre del archivo
            'encriptados' // Disco
        );

        // Real path del disco
        $path = Storage::disk('encriptados')->getDriver()->getAdapter()->getPathPrefix();

        $archivoTxt = $path . $this->baseDatos . '/' . $nombreArchivoTxt;
        $archivoRetorno = $path . $this->baseDatos . '/' . $nombreArchivo . '.csv';

        $error = '';
        try {
            // Ejecuta el comando de consola que permite desencriptar el archivo
            // exec("/usr/bin/gpg --pinentry-mode loopback --passphrase " . $this->passphrase . " --output " . $archivoTxt . " --decrypt " . $path . $this->baseDatos . "/" . $this->nombreArchivo);
            exec("/usr/bin/gpg --batch --passphrase " . $this->passphrase . " --output " . $archivoTxt . " --decrypt " . $path . $this->baseDatos . "/" . $this->nombreArchivo);

            // Agrega la fila de columnas al archivo desencriptado
            $columnas = implode("\t", $this->columnas);
            exec('sed -i \'1s;^;' . $columnas . ' \n;\' ' . $archivoTxt);

            // Convierte el archivo de texto en un archivo csv
            $lineas = [];
            if (($handle = fopen($archivoTxt, "r")) !== FALSE) {
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
