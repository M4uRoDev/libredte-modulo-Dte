<?php

/**
 * LibreDTE
 * Copyright (C) SASCO SpA (https://sasco.cl)
 *
 * Este programa es software libre: usted puede redistribuirlo y/o
 * modificarlo bajo los términos de la Licencia Pública General Affero de GNU
 * publicada por la Fundación para el Software Libre, ya sea la versión
 * 3 de la Licencia, o (a su elección) cualquier versión posterior de la
 * misma.
 *
 * Este programa se distribuye con la esperanza de que sea útil, pero
 * SIN GARANTÍA ALGUNA; ni siquiera la garantía implícita
 * MERCANTIL o de APTITUD PARA UN PROPÓSITO DETERMINADO.
 * Consulte los detalles de la Licencia Pública General Affero de GNU para
 * obtener una información más detallada.
 *
 * Debería haber recibido una copia de la Licencia Pública General Affero de GNU
 * junto a este programa.
 * En caso contrario, consulte <http://www.gnu.org/licenses/agpl.html>.
 */

// namespace del controlador
namespace website\Dte;

/**
 * Controlador para intercambio entre contribuyentes
 * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
 * @version 2018-05-19
 */
class Controller_DteIntercambios extends \Controller_App
{

    /**
     * Acción para mostrar la bandeja de intercambio de DTE
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2017-04-05
     */
    public function listar($p = 1, $soloPendientes = false)
    {
        $Emisor = $this->getContribuyente();
        $filter = [
            'soloPendientes' => $soloPendientes,
            'p' => $p,
        ];
        if (!empty($_GET['search'])) {
            $search = explode(',', $_GET['search']);
            foreach ($search as $s) {
                list($var, $val) = explode(':', $s);
                $filter[$var] = $val;
            }
            $intercambios = $Emisor->getIntercambios($filter);
            $n_intercambios = count($intercambios);
        } else {
            $intercambios = $Emisor->getIntercambios($filter);
            $n_intercambios = (new Model_DteIntercambios())->setWhereStatement(['receptor = :receptor', 'certificacion = :certificacion'], [':receptor'=>$Emisor->rut, ':certificacion'=>$Emisor->config_ambiente_en_certificacion])->count();
        }
        $this->set([
            'Emisor' => $Emisor,
            'intercambios' => $intercambios,
            'n_intercambios' => $n_intercambios,
            'pages' => ceil($n_intercambios / \sowerphp\core\Configure::read('app.registers_per_page')),
            'p' => $p,
            'soloPendientes' => $soloPendientes,
        ]);
    }

    /**
     * Acción para descargar los intercambios pendientes de procesar
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2018-05-19
     */
    public function pendientes()
    {
        $Emisor = $this->getContribuyente();
        $pendientes = $Emisor->getIntercambios();
        if (!$pendientes) {
            \sowerphp\core\Model_Datasource_Session::message(
                'No hay intercambios pendientes', 'warning'
            );
            $this->redirect('/dte/dte_intercambios/listar');
        }
        foreach ($pendientes as &$i) {
            if (is_array($i['documentos'])) {
                $i['documentos'] = implode("\n", $i['documentos']);
            }
        }
        array_unshift($pendientes, array_keys($pendientes[0]));
        \sowerphp\general\Utility_Spreadsheet_CSV::generate($pendientes, $Emisor->rut.'_intercambios_pendientes_'.date('Ymd'));
        exit;
    }

    /**
     * Acción que muestra la página de un intercambio
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2017-08-29
     */
    public function ver($codigo)
    {
        $Emisor = $this->getContribuyente();
        // obtener DTE intercambiado
        $DteIntercambio = new Model_DteIntercambio($Emisor->rut, $codigo, (int)$Emisor->config_ambiente_en_certificacion);
        if (!$DteIntercambio->exists()) {
            \sowerphp\core\Model_Datasource_Session::message(
                'No existe el intercambio solicitado', 'error'
            );
            $this->redirect('/dte/dte_intercambios/listar');
        }
        // obtener firma
        $Firma = $Emisor->getFirma($this->Auth->User->id);
        if (!$Firma) {
            \sowerphp\core\Model_Datasource_Session::message(
                'No hay firma electrónica asociada a la empresa (o bien no se pudo cargar), debe agregar su firma antes de generar DTE', 'error'
            );
            $this->redirect('/dte/admin/firma_electronicas');
        }
        // asignar variables para la vista
        $this->set([
            'Emisor' => $Emisor,
            'DteIntercambio' => $DteIntercambio,
            'EnvioDte' => $DteIntercambio->getEnvioDte(),
            'Documentos' => $DteIntercambio->getDocumentos(),
            'Firma' => $Firma,
        ]);
    }

    /**
     * Acción que permite eliminar un intercambio desde la bandeja
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2018-05-21
     */
    public function eliminar($codigo)
    {
        $Emisor = $this->getContribuyente();
        // obtener DTE intercambiado
        $DteIntercambio = new Model_DteIntercambio($Emisor->rut, $codigo, (int)$Emisor->config_ambiente_en_certificacion);
        if (!$DteIntercambio->exists()) {
            \sowerphp\core\Model_Datasource_Session::message(
                'No existe el intercambio solicitado', 'error'
            );
            $this->redirect('/dte/dte_intercambios/listar');
        }
        // verificar que el intercambio no esté en uso en los documentos recibidos
        if ($DteIntercambio->recibido()) {
            \sowerphp\core\Model_Datasource_Session::message(
                'El intercambio tiene a lo menos un DTE recibido asociado, no se puede eliminar', 'error'
            );
            $this->redirect('/dte/dte_intercambios/ver/'.$codigo);
        }
        // eliminar el intercambio y redireccionar
        $DteIntercambio->delete();
        \sowerphp\core\Model_Datasource_Session::message(
            'Intercambio '.$codigo.' eliminado', 'ok'
        );
        $this->redirect('/dte/dte_intercambios/listar');
    }

    /**
     * Acción que muestra el mensaje del email de intercambio
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-10-08
     */
    public function html($codigo)
    {
        $Emisor = $this->getContribuyente();
        $DteIntercambio = new Model_DteIntercambio($Emisor->rut, $codigo, (int)$Emisor->config_ambiente_en_certificacion);
        if (!$DteIntercambio->exists()) {
            \sowerphp\core\Model_Datasource_Session::message(
                'No existe el intercambio solicitado', 'error'
            );
            $this->redirect('/dte/dte_intercambios/listar');
        }
        $this->layout = null;
        $this->set([
            'html' => $DteIntercambio->mensaje_html ? base64_decode($DteIntercambio->mensaje_html) : 'No hay mensaje HTML',
        ]);
    }

    /**
     * Acción para actualizar la bandeja de intercambio. Guarda los DTEs
     * recibidos por intercambio y guarda los acuses de recibos de DTEs
     * enviados por otros contribuyentes
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2017-03-31
     */
    public function actualizar($dias = 7)
    {
        $Emisor = $this->getContribuyente();
        try {
            $resultado = $Emisor->actualizarBandejaIntercambio($dias);
        } catch (\Exception $e) {
            \sowerphp\core\Model_Datasource_Session::message(
                $e->getMessage(), ($e->getCode()==500 ? 'error' : 'info')
            );
            $this->redirect('/dte/dte_intercambios/listar');
        }
        extract($resultado);
        if ($n_uids>1) {
            $encontrados = 'Se encontraron '.num($n_uids).' correos';
        } else {
            $encontrados = 'Se encontró '.num($n_uids).' correo';
        }
        \sowerphp\core\Model_Datasource_Session::message(
            $encontrados.': EnvioDTE='.num($n_EnvioDTE).',  EnvioRecibos='.num($n_EnvioRecibos).', RecepcionEnvio='.num($n_RecepcionEnvio).', ResultadoDTE='.num($n_ResultadoDTE).' y Omitidos='.num($omitidos), 'ok'
        );
        $this->redirect('/dte/dte_intercambios/listar');
    }

    /**
     * Acción para mostrar el PDF de un EnvioDTE de un intercambio de DTE
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2017-10-06
     */
    public function pdf($codigo, $cedible = false, $emisor = null, $dte = null, $folio = null)
    {
        $Receptor = $this->getContribuyente();
        // obtener DTE intercambiado
        $DteIntercambio = new Model_DteIntercambio($Receptor->rut, $codigo, (int)$Receptor->config_ambiente_en_certificacion);
        if (!$DteIntercambio->exists()) {
            \sowerphp\core\Model_Datasource_Session::message(
                'No existe el intercambio solicitado', 'error'
            );
            $this->redirect('/dte/dte_intercambios/listar');
        }
        // obtener XML que se debe usar
        if ($DteIntercambio->documentos>1 and $emisor and $dte and $folio) {
            $Documento = $DteIntercambio->getDocumento($emisor, $dte, $folio);
            if (!$Documento) {
                \sowerphp\core\Model_Datasource_Session::message(
                    'No existe el DTE T'.$dte.'F'.$folio.' del RUT '.$emisor.' en el intercambio N° '.$codigo, 'error'
                );
                $this->redirect('/dte/dte_intercambios/ver/'.$codigo);
            }
            $xml = base64_encode($Documento->saveXML());
        } else {
            $xml = $DteIntercambio->archivo_xml;
        }
        // armar datos con archivo XML y flag para indicar si es cedible o no
        $data = [
            'xml' => $xml,
            'cedible' => $cedible,
        ];
        // consultar servicio web de LibreDTE
        $ApiDtePdfClient = $DteIntercambio->getEmisor()->getApiClient('dte_pdf');
        if (!$ApiDtePdfClient) {
            $rest = new \sowerphp\core\Network_Http_Rest();
            $rest->setAuth($this->Auth->User ? $this->Auth->User->hash : \sowerphp\core\Configure::read('api.default.token'));
            $response = $rest->post($this->request->url.'/api/utilidades/documentos/generar_pdf', $data);
        }
        // consultar servicio web del contribuyente
        else {
            $response = $ApiDtePdfClient->post($ApiDtePdfClient->url, $data);
        }
        // procesar respuesta
        if ($response['status']['code']!=200) {
            \sowerphp\core\Model_Datasource_Session::message($response['body'], 'error');
            return;
        }
        // si dió código 200 se entrega la respuesta del servicio web
        foreach (['Content-Disposition', 'Content-Length', 'Content-Type'] as $header) {
            if (isset($response['header'][$header]))
                header($header.': '.$response['header'][$header]);
        }
        echo $response['body'];
        exit;
    }

    /**
     * Acción que descarga el XML del documento intercambiado
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-09-27
     */
    public function xml($codigo)
    {
        $Emisor = $this->getContribuyente();
        // obtener DTE emitido
        $DteIntercambio = new Model_DteIntercambio($Emisor->rut, $codigo, (int)$Emisor->config_ambiente_en_certificacion);
        if (!$DteIntercambio->exists()) {
            \sowerphp\core\Model_Datasource_Session::message(
                'No existe el intercambio solicitado', 'error'
            );
            $this->redirect('/dte/dte_intercambios/listar');
        }
        // entregar XML
        $xml = base64_decode($DteIntercambio->archivo_xml);
        header('Content-Type: application/xml; charset=ISO-8859-1');
        header('Content-Length: '.strlen($xml));
        header('Content-Disposition: attachement; filename="'.$DteIntercambio->archivo.'"');
        print $xml;
        exit;
    }

    /**
     * Acción que entrega los XML del resultado de la revisión del intercambio
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2015-09-28
     */
    public function resultados_xml($codigo)
    {
        $Emisor = $this->getContribuyente();
        // obtener DTE emitido
        $DteIntercambio = new Model_DteIntercambio($Emisor->rut, $codigo, (int)$Emisor->config_ambiente_en_certificacion);
        if (!$DteIntercambio->exists()) {
            \sowerphp\core\Model_Datasource_Session::message(
                'No existe el intercambio solicitado', 'error'
            );
            $this->redirect('/dte/dte_intercambios/listar');
        }
        // si no hay XML error
        if (!$DteIntercambio->recepcion_xml and !$DteIntercambio->recibos_xml and !$DteIntercambio->resultado_xml) {
            \sowerphp\core\Model_Datasource_Session::message(
                'No existen archivos de resultado generados, no se ha procesado aun el intercambio', 'error'
            );
            $this->redirect(str_replace('resultados_xml', 'ver', $this->request->request));
        }
        // agregar a archivo comprimido y entregar
        $dir = TMP.'/resultado_intercambio_'.$Emisor->rut.'-'.$Emisor->dv.'_'.$DteIntercambio->codigo;
        if (is_dir($dir)) {
            \sowerphp\general\Utility_File::rmdir($dir);
        }
        if (!mkdir($dir)) {
            \sowerphp\core\Model_Datasource_Session::message(
                'No fue posible crear el directorio temporal para los XML', 'error'
            );
            $this->redirect(str_replace('resultados_xml', 'ver', $this->request->request));
        }
        if ($DteIntercambio->recepcion_xml) {
            file_put_contents($dir.'/RecepcionDTE.xml', base64_decode($DteIntercambio->recepcion_xml));
        }
        if ($DteIntercambio->recibos_xml) {
            file_put_contents($dir.'/EnvioRecibos.xml', base64_decode($DteIntercambio->recibos_xml));
        }
        if ($DteIntercambio->resultado_xml) {
            file_put_contents($dir.'/ResultadoDTE.xml', base64_decode($DteIntercambio->resultado_xml));
        }
        \sowerphp\general\Utility_File::compress($dir, ['format'=>'zip', 'delete'=>true]);
        exit;
    }

    /**
     * Acción que procesa y responde al intercambio recibido
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2018-05-20
     */
    public function responder($codigo)
    {
        $Emisor = $this->getContribuyente();
        // si no se viene por post error
        if (!isset($_POST['submit'])) {
            \sowerphp\core\Model_Datasource_Session::message(
                'No puede acceder de forma directa a '.$this->request->request, 'error'
            );
            $this->redirect(str_replace('responder', 'ver', $this->request->request));
        }
        // obtener objeto de intercambio
        $DteIntercambio = new Model_DteIntercambio($Emisor->rut, $codigo, (int)$Emisor->config_ambiente_en_certificacion);
        if (!$DteIntercambio->exists()) {
            \sowerphp\core\Model_Datasource_Session::message(
                'No existe el intercambio solicitado', 'error'
            );
            $this->redirect('/dte/dte_intercambios/listar');
        }
        // armar documentos con sus respuestas
        $documentos = [];
        $n_dtes = count($_POST['TipoDTE']);
        for ($i=0; $i<$n_dtes; $i++) {
            $documentos[] = [
                'TipoDTE' => $_POST['TipoDTE'][$i],
                'Folio' => $_POST['Folio'][$i],
                'FchEmis' => $_POST['FchEmis'][$i],
                'RUTEmisor' => $_POST['RUTEmisor'][$i],
                'RUTRecep' => $_POST['RUTRecep'][$i],
                'MntTotal' => $_POST['MntTotal'][$i],
                'EstadoRecepDTE' => $_POST['rcv_accion_codigo'][$i],
                'RecepDTEGlosa' => $_POST['rcv_accion_glosa'][$i],
            ];
        }
        // armar configuración extra para la respuesta
        $config = [
            'user_id' => $this->Auth->User->id,
            'NmbContacto' => $_POST['NmbContacto'],
            'MailContacto' => $_POST['MailContacto'],
            'sucursal' => $_POST['sucursal'],
            'Recinto' => $_POST['Recinto'],
            'responder_a' => $_POST['responder_a'],
            'periodo' => $_POST['periodo'],
        ];
        // generar respuesta
        try {
            $resultado = $DteIntercambio->responder($documentos, $config);
            if ($resultado['email']===true) {
                $msg = 'Se procesaron DTEs de intercambio y se envió la respuesta a: '.$config['responder_a'];
                if ($resultado['rc']['estado']) {
                    $msg .= '<br/><br/>- '.implode('<br/> -', $resultado['rc']['estado']);
                }
                \sowerphp\core\Model_Datasource_Session::message($msg, 'ok');
            } else {
                $msg = 'Se procesaron DTEs de intercambio, pero no fue posible enviar el email, por favor intente nuevamente.<br /><em>'.$resultado['email']['message'].'</em>';
                if ($resultado['rc']['estado']) {
                    $msg .= '<br/><br/>- '.implode('<br/> -', $resultado['rc']['estado']);
                }
                \sowerphp\core\Model_Datasource_Session::message($msg, 'warning');
            }
        } catch (\Exception $e) {
            \sowerphp\core\Model_Datasource_Session::message($e->getMessage(), 'error');
        }
        // redireccionar
        $this->redirect(str_replace('responder', 'ver', $this->request->request));
    }

    /**
     * Acción que permite ingresar una acción del registro a un DTE
     * @author Esteban De La Fuente Rubio, DeLaF (esteban[at]sasco.cl)
     * @version 2018-05-19
     */
    public function dte_rcv($emisor, $dte, $folio)
    {
        list($emisor_rut, $emisor_dv) = explode('-', str_replace('.', '', $emisor));
        $Contribuyente = $this->getContribuyente();
        $Firma = $Contribuyente->getFirma($this->Auth->User->id);
        if (!$Firma) {
            \sowerphp\core\Model_Datasource_Session::message(
                'No existe firma asociada', 'error'
            );
            $this->redirect('/dte/dte_intercambios/listar');
        }
        // hacer conexión al SII para RCV
        try {
            $RCV = new \sasco\LibreDTE\Sii\RegistroCompraVenta($Firma);
        } catch (\Exception $e) {
            \sowerphp\core\Model_Datasource_Session::message($e->getMessage(), 'error');
            $this->redirect($this->request->request);
        }
        // procesar formulario (antes de asignar variables para que se refleje en la vista)
        if (isset($_POST['submit'])) {
            list($emisor_rut, $emisor_dv) = explode('-', $emisor);
            try {
                $r = $RCV->ingresarAceptacionReclamoDoc($emisor_rut, $emisor_dv, $dte, $folio, $_POST['accion']);
                if ($r) {
                    \sowerphp\core\Model_Datasource_Session::message($r['glosa'], !$r['codigo']?'ok':'error');
                } else {
                    \sowerphp\core\Model_Datasource_Session::message('No fue posible ingresar la acción del DTE al SII', 'error');
                }
            } catch (\Exception $e) {
                \sowerphp\core\Model_Datasource_Session::message($e->getMessage(), 'error');
            }
        }
        // asignar variables para la vista
        try {
            $eventos = $RCV->listarEventosHistDoc($emisor_rut, $emisor_dv, $dte, $folio);
            $cedible = $RCV->consultarDocDteCedible($emisor_rut, $emisor_dv, $dte, $folio);
            $fecha_recepcion = $RCV->consultarFechaRecepcionSii($emisor_rut, $emisor_dv, $dte, $folio);
            $this->set([
                'Emisor' => new \website\Dte\Model_Contribuyente($emisor_rut),
                'DteTipo' => new \website\Dte\Admin\Mantenedores\Model_DteTipo($dte),
                'folio' => $folio,
                'eventos' => $eventos!==false ? $eventos : null,
                'cedible' => $cedible!==false ? $cedible : null,
                'fecha_recepcion' => $fecha_recepcion!==false ? $fecha_recepcion : null,
            ]);
        } catch (\Exception $e) {
            \sowerphp\core\Model_Datasource_Session::message($e->getMessage(), 'error');
            $this->redirect('/dte/dte_intercambios/listar');
        }
    }

}
