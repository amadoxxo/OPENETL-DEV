<?php

namespace App\Http\Modulos\Utils\ExcelExports;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Events\BeforeExport;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Contracts\Support\Responsable;

class ExcelExport implements FromArray, WithHeadings, ShouldAutoSize, WithEvents, WithTitle, Responsable {
    use Exportable;

    /**
     * Datos a ser  exportados.
     * 
     * @var array
     */
    protected $data;

    /**
     * Encabezados por defecto.
     * 
     * @var array
     */
    protected $headingsDefault;

    /**
     * Encabezados de cabecera.
     * 
     * @var array
     */
    protected $headingsCabecera;

    /**
     * Encabezados de detalle.
     * 
     * @var array
     */
    protected $headingsDetalle;

    /**
     * Nombre del archivo.
     * 
     * @var string
     */
    private $fileName;

    /**
     * Titulo de la hoja de cálculo.
     * 
     * @var string
     */
    protected $title;

    /**
     * @var string
     */
    protected $interface;

    /**
     * @var string 
     */
    protected $letraColumna;
    
    /**
     * @var string
     */
    protected $letraColAdicional;

    /**
     * @param string $__title
     * @param array $__headingsDefault
     * @param array $__data
     * @param string $__interface
     * @param array $__headingsCabecera
     * @param array $__headingsDetalle
     * @param string $__letraColumna
     * @param string $__letraColAdicional
     */
    public function __construct($__title, 
        $__headingsDefault, 
        $__data = [],
        $__interface = '', 
        $__headingsCabecera = [], 
        $__headingsDetalle = [], 
        $__letraColumna = '', 
        $__letraColAdicional = '') {
        $this->headingsDefault = $__headingsDefault;
        $this->headingsCabecera = $__headingsCabecera;
        $this->headingsDetalle = $__headingsDetalle;
        $this->interface = $__interface;
        $this->letraColumna = $__letraColumna;
        $this->letraColAdicional = $__letraColAdicional;
        $this->data = $__data;
        $this->title = $__title;
        $this->fileName = $__title . '.xlsx';
    }

    /**
     * @return array
     */
    public function array(): array {
        return $this->data;
    }

    /**
     * @return array
     */
    public function headings(): array {
        return $this->headingsDefault;
    }

    /**
     * @return string
     */
    public function title(): string {
        if (strlen($this->title) > 30)
            $this->title = substr($this->title, 0, 30);

        return $this->title;
    }

    /**
     * Determina la letra de la última columna dado el número de Columnas.
     * 
     * @param int $columnNumber
     * @return string
     */
    private function nombreColumna($columnNumber) {
        $nombre = '';

        while ($columnNumber > 0) {
            $rem = $columnNumber % 26;
            if ($rem === 0) {
                $nombre = 'Z' . $nombre;
                $columnNumber = ($columnNumber / 26) - 1;
            }
            else {
                $nombre = chr(65 + $rem-1) . $nombre;
                $columnNumber = intval($columnNumber / 26);
            }
        }
        return $nombre;
    }

    /**
     * @return array
     */
    public function registerEvents(): array {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                
                $event->sheet->getParent()->getDefaultStyle()->applyFromArray([
                    'font' => [
                        'name' => 'Calibri',
                        'size' => 12,
                        'bold' => false
                    ],
                ]);

                $event->sheet->getRowDimension('1')->setRowHeight(30);

                if ($this->interface === 'NcNd' || $this->interface === 'Fc') {
                    // Columna más alta con información
                    $highestColumn = $event->sheet->getHighestColumn();

                    $event->sheet->getStyle('A1:' . $highestColumn . '1')->applyFromArray([
                        'font' => [
                            'name' => 'Calibri',
                            'bold' => true,
                            'size' => 14,
                        ],
                        'vertical-align' => [
                            'vertical' => Alignment::VERTICAL_CENTER,
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                        ],
                        'alignment' => [
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                        ]
                    ]);
    
                    if ($this->interface === 'Fc') {
                        // Columnas cabecera default azules de Fc       
                        $event->sheet->getStyle('A1:Z1')->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'color' => [
                                    'rgb' => 'c9daf8'
                                ],
                            ]
                        ]);

                        // Columnas cabecera adicionales azules de Fc
                        if (count($this->headingsCabecera) > 0) {
                            foreach ($this->headingsCabecera as $columna) {
                                $this->letraColumna++;
                            }
                            
                            $event->sheet->getStyle('X1:' . $this->letraColumna . '1')->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'color' => [
                                        'rgb' => '78a3ed'
                                    ]
                                ]
                            ]);
                        }
                    }

                    if ($this->interface === 'NcNd') { 
                        // Columnas cabecera default azules de NcNd
                        $event->sheet->getStyle('A1:' . $this->letraColumna . '1')->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'color' => [
                                    'rgb' => 'c9daf8'
                                ]
                            ]
                        ]);

                        if (count($this->headingsCabecera) > 0) {
                            foreach ($this->headingsCabecera as $columna) {
                                $this->letraColumna++;
                            }

                            $event->sheet->getStyle($this->letraColAdicional . '1:' . $this->letraColumna . '1')->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'color' => [
                                        'rgb' => '78a3ed'
                                    ]
                                ]
                            ]);
                        }
                    }


                    // Columnas Amarillas
                    for ($i = 1; $i <= 20; $i++) {
                        $this->letraColumna++;
                        $event->sheet->getStyle($this->letraColumna . '1')->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'color' => [
                                    'rgb' => 'fff2cc'
                                ]
                            ]
                        ]);
                    }

                    // Columnas Rojas
                    for ($i = 1; $i <= 16; $i++) {
                        $this->letraColumna++;
                        $event->sheet->getStyle($this->letraColumna . '1')->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'color' => [
                                    'rgb' => 'f4cccc'
                                ]
                            ]
                        ]);
                    }

                    if (count($this->headingsDetalle) > 0) {
                        
                        // Columnas adicionales de detalle
                        $this->letraColumna++;
                        $event->sheet->getStyle($this->letraColumna . '1:' . $highestColumn . '1')->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'color' => [
                                    'rgb' => 'fce5cd'
                                ]
                            ]
                        ]);    
                    }

                    
                } else {
                    $event->sheet->getStyle('A1:' . $this->nombreColumna(count($this->headingsDefault)) .'1')->applyFromArray([
                        'font' => [
                            'name' => 'Calibri',
                            'bold' => true,
                            'size' => 14,
                        ],
                        'vertical-align' => [
                            'vertical' => Alignment::VERTICAL_CENTER,
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                        ],
                        'alignment' => [
                            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'color' => [
                                'rgb' => 'c9daf8'
                            ]
                        ]
                    ]);
                }
                
                $event->sheet->freezePane('A2'); 
            },
            BeforeExport::class  => function(BeforeExport $event) {
                $event->writer->getProperties()
                    ->setCreator('openETL')
                    ->setLastModifiedBy('openETL');
            }
        ];
    }
}

