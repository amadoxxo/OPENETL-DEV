<?php


namespace App\Http\Traits;


/**
 * Motor para convertir cantidades numericas de moneda a letras.
 *
 * Class NumToLetrasEngine
 * @package App\Http\Modulos\Utils
 */
trait NumToLetrasEngine
{
    /**
     * @function num2letras_en ()
     * @abstract Dado un número lo devuelve escrito en letras en inglés
     * @param $num number - Número a convertir (máximo dos decimales)
     * @result string - Devuelve el número escrito en letras.
     * @return string
     */
    public static function num2letras_en($num) {
        $decones = array(
            '01' => 'Zero One',
            '02' => 'Zero Two',
            '03' => 'Zero Three',
            '04' => 'Zero Four',
            '05' => 'Zero Five',
            '06' => 'Zero Six',
            '07' => 'Zero Seven',
            '08' => 'Zero Eight',
            '09' => 'Zero Nine',
            10 => 'Ten',
            11 => 'Eleven',
            12 => 'Twelve',
            13 => 'Thirteen',
            14 => 'Fourteen',
            15 => 'Fifteen',
            16 => 'Sixteen',
            17 => 'Seventeen',
            18 => 'Eighteen',
            19 => 'Nineteen'
        );
        $ones = array(
            0 => '',
            1 => 'One',
            2 => 'Two',
            3 => 'Three',
            4 => 'Four',
            5 => 'Five',
            6 => 'Six',
            7 => 'Seven',
            8 => 'Eight',
            9 => 'Nine',
            10 => 'Ten',
            11 => 'Eleven',
            12 => 'Twelve',
            13 => 'Thirteen',
            14 => 'Fourteen',
            15 => 'Fifteen',
            16 => 'Sixteen',
            17 => 'Seventeen',
            18 => 'Eighteen',
            19 => 'Nineteen'
        );
        $tens = array(
            0 => '',
            1 => 'Ten',
            2 => 'Twenty',
            3 => 'Thirty',
            4 => 'Forty',
            5 => 'Fifty',
            6 => 'Sixty',
            7 => 'Seventy',
            8 => 'Eighty',
            9 => 'Ninety'
        );
        $hundreds = array(
            'Hundred',
            'Thousand',
            'Million',
            'Billion',
            'Trillion',
            'Quadrillion'
        );
        $num = number_format($num, 2, '.', ',');
        $num_arr = explode('.', $num);
        $wholenum = $num_arr[0];
        $decnum = $num_arr[1];
        $whole_arr = array_reverse(explode(",", $wholenum));
        krsort($whole_arr);
        $rettxt = "";
        foreach ($whole_arr as $key => $i) {
            if ($i < 20) {
                $rettxt .= $ones[abs($i)];
            } elseif ($i < 100) {
                $rettxt .= $tens[substr($i, 0, 1)];
                if (strlen($i) == 3) {
                    $rettxt .= ' ' . $tens[substr($i, 1, 1)];
                    $rettxt .= ($ones[substr($i, 2, 1)] != '') ? '-' . $ones[substr($i, 2, 1)] : ' ';
                } else {
                    $rettxt .= ' ' . $ones[substr($i, 1, 1)];
                }
            } else {
                $rettxt .= $ones[substr($i, 0, 1)] . ' ' . $hundreds[0];
                $rettxt .= ' ' . $tens[substr($i, 1, 1)];
                if (substr($i, 2, 2) < 20) {
                    $rettxt .= ($ones[substr($i, 2, 1)] != '' && $ones[substr($i, 1, 1)] != '') ? '-' . $ones[substr($i, 2, 1)] : ' ' . $ones[substr($i, 2, 1)];
                } else {
                    $rettxt .= ' ' . $tens[substr($i, 2, 1)];
                    $rettxt .= ($ones[substr($i, 3, 1)] != '') ? '-' . $ones[substr($i, 3, 1)] : ' ';
                }
            }
            if ($key > 0) {
                $rettxt .= ' ' . $hundreds[$key] . ' ';
            }
        }
        $rettxt = $rettxt . " dollars";

        if ($decnum > 0) {
            $rettxt .= " and ";
            if ($decnum < 20) {
                $rettxt .= $decones[$decnum];
            } elseif ($decnum < 100) {
                $rettxt .= $tens[substr($decnum, 0, 1)];
                $rettxt .= " " . $ones[substr($decnum, 1, 1)];
            }
            $rettxt = $rettxt . " cents";
        } else {
            $rettxt = $rettxt . " and zero cents";
        }

        $rettxt = strtoupper(str_replace('  ', ' ', $rettxt));

        return $rettxt;
    }

    /**
     * @function num2letras ()
     * @abstract Dado un número lo devuelve escrito en letras en español
     * @param $num number - Número a convertir (máximo dos decimales) y el número no debe tener seprador de miles
     * @param $fem bool - Forma femenina (true) o no (false).
     * @param $dec bool - Con decimales (true) o no (false).
     * @param string $moneda Por defecto es Pesos Colombianos
     * @result string - Devuelve el n?mero escrito en letra.
     * @return string
     */
    public static function num2letras($num, $fem = false, $dec = true, $moneda = 'COP') {
        $end_num = '';
        $matuni[2] = "DOS";
        $matuni[3] = "TRES";
        $matuni[4] = "CUATRO";
        $matuni[5] = "CINCO";
        $matuni[6] = "SEIS";
        $matuni[7] = "SIETE";
        $matuni[8] = "OCHO";
        $matuni[9] = "NUEVE";
        $matuni[10] = "DIEZ";
        $matuni[11] = "ONCE";
        $matuni[12] = "DOCE";
        $matuni[13] = "TRECE";
        $matuni[14] = "CATORCE";
        $matuni[15] = "QUINCE";
        $matuni[16] = "DIECISEIS";
        $matuni[17] = "DIECISIETE";
        $matuni[18] = "DIECIOCHO";
        $matuni[19] = "DIECINUEVE";
        $matuni[20] = "VEINTE";
        $matunisub[2] = "DOS";
        $matunisub[3] = "TRES";
        $matunisub[4] = "CUATRO";
        $matunisub[5] = "QUIN";
        $matunisub[6] = "SEIS";
        $matunisub[7] = "SETE";
        $matunisub[8] = "OCHO";
        $matunisub[9] = "NOVE";
        $matdec[2] = "VEINT";
        $matdec[3] = "TREINTA";
        $matdec[4] = "CUARENTA";
        $matdec[5] = "CINCUENTA";
        $matdec[6] = "SESENTA";
        $matdec[7] = "SETENTA";
        $matdec[8] = "OCHENTA";
        $matdec[9] = "NOVENTA";
        $matsub[3] = 'MILL';
        $matsub[5] = 'BILL';
        $matsub[7] = 'MILL';
        $matsub[9] = 'TRILL';
        $matsub[11] = 'MILL';
        $matsub[13] = 'BILL';
        $matsub[15] = 'MILL';
        $matmil[4] = 'MILLONES';
        $matmil[6] = 'BILLONES';
        $matmil[7] = 'DE BILLONES';
        $matmil[8] = 'MILLONES DE BILLONES';
        $matmil[10] = 'TRILLONES';
        $matmil[11] = 'DE TRILLONES';
        $matmil[12] = 'MILLONES DE TRILLONES';
        $matmil[13] = 'DE TRILLONES';
        $matmil[14] = 'BILLONES DE TRILLONES';
        $matmil[15] = 'DE BILLONES DE TRILLONES';
        $matmil[16] = 'MILLONES DE BILLONES DE TRILLONES';

        if ($num == "0.00" && $moneda == 'COP') {
            $end_num = ' CERO PESOS M/CTE';
        } elseif ($num == "0.00" && $moneda == 'USD') {
            $end_num = ' CERO DOLARES CON CERO CENTAVOS';
        } else {
            //Zi hack
            $float = explode('.', $num);
            $num = $float[0];
            if (count($float) == 1) {
                $float[1] = 0;
            }

            $num = trim((string)@$num);
            if ($num[0] == '-') {
                $neg = 'menos ';
                $num = substr($num, 1);
            } else {
                $neg = '';
            }

            if(strlen($num) > 1) $num = ltrim($num, '0');
            if ($num[0] < '1' or $num[0] > 9) $num = '0' . $num;
            $zeros = true;
            $punt = false;
            $ent = '';
            $fra = '';
            for ($c = 0; $c < strlen($num); $c++) {
                $n = $num[$c];
                if (!(strpos(".,'''", $n) === false)) {
                    if ($punt) break;
                    else {
                        $punt = true;
                        continue;
                    }
                } elseif (!(strpos('0123456789', $n) === false)) {
                    if ($punt) {
                        if ($n != '0') $zeros = false;
                        $fra .= $n;
                    } else
                        $ent .= $n;
                } else
                    break;
            }
            $ent = '     ' . $ent;
            if ($dec and $fra and !$zeros) {
                $fin = ' coma';
                for ($n = 0; $n < strlen($fra); $n++) {
                    if (($s = $fra[$n]) == '0')
                        $fin .= ' cero';
                    elseif ($s == '1')
                        $fin .= $fem ? ' una' : ' un';
                    else
                        $fin .= ' ' . $matuni[$s];
                }
            } else
                $fin = '';

            $tex = '';
            $sub = 0;
            $mils = 0;
            $neutro = false;
            if ((int)$ent === 0) $tex = ' Cero';
            while (($num = substr($ent, -3)) != '   ') {
                $ent = substr($ent, 0, -3);
                if (++$sub < 3 and $fem) {
                    $matuni[1] = 'una';
                    $subcent = 'as';
                } else {
                    $matuni[1] = $neutro ? 'un' : 'uno';
                    $subcent = 'os';
                }
                $t = '';
                $n2 = substr($num, 1);
                if ($n2 == '00') {
                    //
                } elseif ($n2 < 21)
                    $t = ' ' . $matuni[(int)$n2];
                elseif ($n2 < 30) {
                    $n3 = $num[2];
                    if ($n3 != 0) $t = 'i' . $matuni[$n3];
                    $n2 = $num[1];
                    $t = ' ' . $matdec[$n2] . $t;
                } else {
                    $n3 = $num[2];
                    if ($n3 != 0) $t = ' y ' . $matuni[$n3];
                    $n2 = $num[1];
                    $t = ' ' . $matdec[$n2] . $t;
                }
                $n = $num[0];
                if ($n == 1) {
                    // $t = ' ciento' . $t;
                    if (substr($num, 1) == "00") {
                        $t = ' cien' . $t;
                    } else {
                        $t = ' ciento' . $t;
                    }
                } elseif ($n == 5) {
                    $t = ' ' . $matunisub[$n] . 'ient' . $subcent . $t;
                } elseif ($n != 0) {
                    $t = ' ' . $matunisub[$n] . 'cient' . $subcent . $t;
                }
                if ($sub == 1) {
                } elseif (!isset($matsub[$sub])) {
                    if ($num == 1) {
                        $t = ' mil';
                    } elseif ($num > 1) {
                        $t .= ' mil';
                    }
                } elseif ($num == 1) {
                    $t .= ' ' . $matsub[$sub] . 'on';
                } elseif ($num > 1) {
                    $t .= ' ' . $matsub[$sub] . 'ones';
                }
                if ($num == '000') $mils++;
                elseif ($mils != 0) {
                    if (isset($matmil[$sub])) $t .= ' ' . $matmil[$sub];
                    $mils = 0;
                }
                $neutro = true;
                $tex = $t . $tex;
            }
            $tex = $neg . substr($tex, 1) . $fin;
            if ($moneda == 'COP') {
                if ($float[1] != '' && $float[1] > 0)
                    $end_num = strtoupper($tex) . ' PESOS ' . (string)$float[1] . '/100';
                else
                    $end_num = strtoupper($tex) . ' PESOS M/CTE';
            } elseif ($moneda == 'USD') {
                $end_num = strtoupper($tex) . ' DOLARES CON ' . $float[1] . ' CENTAVOS';
            }
        }
        return $end_num;
    }

    /**
     * @function num3letras ()
     * @abstract Dado un número lo devuelve escrito en letras en español y los centavos en letras
     * @param $num number - Número a convertir (máximo dos decimales) y el número no debe tener seprador de miles
     * @param $fem bool - Forma femenina (true) o no (false).
     * @param $dec bool - Con decimales (true) o no (false).
     * @param string $moneda Por defecto es Pesos Colombianos
     * @result string - Devuelve el n?mero escrito en letra.
     * @return string
     */
    public static function num3letras($num, $fem = false, $dec = true, $moneda = 'COP') {
        $matuni[1] = "UNO";
        $matuni[2] = "DOS";
        $matuni[3] = "TRES";
        $matuni[4] = "CUATRO";
        $matuni[5] = "CINCO";
        $matuni[6] = "SEIS";
        $matuni[7] = "SIETE";
        $matuni[8] = "OCHO";
        $matuni[9] = "NUEVE";
        $matuni[10] = "DIEZ";
        $matuni[11] = "ONCE";
        $matuni[12] = "DOCE";
        $matuni[13] = "TRECE";
        $matuni[14] = "CATORCE";
        $matuni[15] = "QUINCE";
        $matuni[16] = "DIECISEIS";
        $matuni[17] = "DIECISIETE";
        $matuni[18] = "DIECIOCHO";
        $matuni[19] = "DIECINUEVE";
        $matuni[20] = "VEINTE";
        $matunisub[2] = "DOS";
        $matunisub[3] = "TRES";
        $matunisub[4] = "CUATRO";
        $matunisub[5] = "QUIN";
        $matunisub[6] = "SEIS";
        $matunisub[7] = "SETE";
        $matunisub[8] = "OCHO";
        $matunisub[9] = "NOVE";

        $matdec[2] = "VEINT";
        $matdec[3] = "TREINTA";
        $matdec[4] = "CUARENTA";
        $matdec[5] = "CINCUENTA";
        $matdec[6] = "SESENTA";
        $matdec[7] = "SETENTA";
        $matdec[8] = "OCHENTA";
        $matdec[9] = "NOVENTA";
        $matsub[3] = 'MILL';
        $matsub[5] = 'BILL';
        $matsub[7] = 'MILL';
        $matsub[9] = 'TRILL';
        $matsub[11] = 'MILL';
        $matsub[13] = 'BILL';
        $matsub[15] = 'MILL';
        $matmil[4] = 'MILLONES';
        $matmil[6] = 'BILLONES';
        $matmil[7] = 'DE BILLONES';
        $matmil[8] = 'MILLONES DE BILLONES';
        $matmil[10] = 'TRILLONES';
        $matmil[11] = 'DE TRILLONES';
        $matmil[12] = 'MILLONES DE TRILLONES';
        $matmil[13] = 'DE TRILLONES';
        $matmil[14] = 'BILLONES DE TRILLONES';
        $matmil[15] = 'DE BILLONES DE TRILLONES';
        $matmil[16] = 'MILLONES DE BILLONES DE TRILLONES';

        if ($num == "0.00" && $moneda == 'COP') {
            $end_num = ' CERO PESOS M/CTE';
        } elseif ($num == "0.00" && $moneda == 'USD') {
            $end_num = ' CERO DOLARES CON CERO CENTAVOS';
        } else {
            //Zi hack
            $float = explode('.', $num);
            $num = $float[0];
            if (count($float) == 1) {
                $float[1] = 0;
            }

            $num = trim((string)@$num);
            if ($num[0] == '-') {
                $neg = 'menos ';
                $num = substr($num, 1);
            } else {
                $neg = '';
            }

            if(strlen($num) > 1) $num = ltrim($num, '0');
            if ($num[0] < '1' or $num[0] > 9) $num = '0' . $num;
            $zeros = true;
            $punt = false;
            $ent = '';
            $fra = '';
            for ($c = 0; $c < strlen($num); $c++) {
                $n = $num[$c];
                if (!(strpos(".,'''", $n) === false)) {
                    if ($punt) break;
                    else {
                        $punt = true;
                        continue;
                    }
                } elseif (!(strpos('0123456789', $n) === false)) {
                    if ($punt) {
                        if ($n != '0') $zeros = false;
                        $fra .= $n;
                    } else
                        $ent .= $n;
                } else
                    break;
            }
            $ent = '     ' . $ent;
            if ($dec and $fra and !$zeros) {
                $fin = ' coma';
                for ($n = 0; $n < strlen($fra); $n++) {
                    if (($s = $fra[$n]) == '0')
                        $fin .= ' cero';
                    elseif ($s == '1')
                        $fin .= $fem ? ' una' : ' un';
                    else
                        $fin .= ' ' . $matuni[$s];
                }
            } else
                $fin = '';
                
            $tex = '';
            $sub = 0;
            $mils = 0;
            $neutro = false;
            if ((int)$ent === 0) $tex = ' Cero';
            while (($num = substr($ent, -3)) != '   ') {
                $ent = substr($ent, 0, -3);
                if (++$sub < 3 and $fem) {
                    $matuni[1] = 'una';
                    $subcent = 'as';
                } else {
                    $matuni[1] = $neutro ? 'un' : 'uno';
                    $subcent = 'os';
                }
                $t = '';
                $n2 = substr($num, 1);
                if ($n2 == '00') {
                    //
                } elseif ($n2 < 21)
                    $t = ' ' . $matuni[(int)$n2];
                elseif ($n2 < 30) {
                    $n3 = $num[2];
                    if ($n3 != 0) $t = 'i' . $matuni[$n3];
                    $n2 = $num[1];
                    $t = ' ' . $matdec[$n2] . $t;
                } else {
                    $n3 = $num[2];
                    if ($n3 != 0) $t = ' y ' . $matuni[$n3];
                    $n2 = $num[1];
                    $t = ' ' . $matdec[$n2] . $t;
                }
                $n = $num[0];
                if ($n == 1) {
                    // $t = ' ciento' . $t;
                    if (substr($num, 1) == "00") {
                        $t = ' cien' . $t;
                    } else {
                        $t = ' ciento' . $t;
                    }
                } elseif ($n == 5) {
                    $t = ' ' . $matunisub[$n] . 'ient' . $subcent . $t;
                } elseif ($n != 0) {
                    $t = ' ' . $matunisub[$n] . 'cient' . $subcent . $t;
                }
                if ($sub == 1) {
                } elseif (!isset($matsub[$sub])) {
                    if ($num == 1) {
                        $t = ' mil';
                    } elseif ($num > 1) {
                        $t .= ' mil';
                    }
                } elseif ($num == 1) {
                    $t .= ' ' . $matsub[$sub] . 'on';
                } elseif ($num > 1) {
                    $t .= ' ' . $matsub[$sub] . 'ones';
                }
                if ($num == '000') $mils++;
                elseif ($mils != 0) {
                    if (isset($matmil[$sub])) $t .= ' ' . $matmil[$sub];
                    $mils = 0;
                }
                $neutro = true;
                $tex = $t . $tex;
            }
            $tex = $neg . substr($tex, 1) . $fin;

            $texto_moneda = ($moneda == 'COP') ? "PESOS" : (($moneda == 'USD') ? "DOLARES" : "");
            if ($float[1] != '' && $float[1] > 0) {
                if ((int)$float[1] <= 20) {
                    $end_num = strtoupper($tex) . ' ' . $texto_moneda . ' CON ' . $matuni[(int)$float[1]] . ' CENTAVOS';
                } else {
                    $cen_dec = substr($float[1], 0, 1);
                    $cen_uni = substr($float[1], 1, 1);
                    if ($cen_uni > 0) {
                        if ((int)$float[1] <= 30) {
                            $end_num = strtoupper($tex) . ' ' . $texto_moneda . ' CON ' . $matdec[(int)$cen_dec] . 'I' . $matuni[(int)$cen_uni] . ' CENTAVOS';
                        } else {
                            $end_num = strtoupper($tex) . ' ' . $texto_moneda . ' CON ' . $matdec[(int)$cen_dec] . ' Y ' . $matuni[(int)$cen_uni] . ' CENTAVOS';
                        }
                    } else {
                        $end_num = strtoupper($tex) . ' ' . $texto_moneda . ' CON ' . $matdec[(int)$cen_dec] . ' CENTAVOS';
                    }
                }
            } else
                $end_num = strtoupper($tex) . ' ' . $texto_moneda . ' M/CTE';
        }
        return $end_num;
    }
}