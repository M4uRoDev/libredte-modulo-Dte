<ul class="nav nav-pills pull-right">
    <li>
        <a href="<?=$_base?>/dte/dte_ventas/ver/<?=$periodo?>" title="Volver a la IEV del período <?=$periodo?>">
            Volver a la IEV <?=$periodo?>
        </a>
    </li>
</ul>
<script>
function get_codigo_reemplazo() {
    $.get(_base+'/api/dte/dte_ventas/codigo_reemplazo/<?=$periodo?>/<?=$Emisor->rut?>', function(codigo) {
        document.getElementById('CodAutRecField').value = codigo;
    }).fail(function(error){alert(error.responseJSON)});
}
</script>
<div class="page-header"><h1>Rectificación IEV para el período <?=$periodo?></h1></div>
<?php
$f = new \sowerphp\general\View_Helper_Form();
echo $f->begin([
    'action' => $_base.'/dte/dte_ventas/enviar_sii/'.$periodo,
    'onsubmit'=>'Form.check() && Form.checkSend(\'¿Está seguro de enviar la rectificación del libro?\')'
]);
echo $f->input([
    'name' => 'CodAutRec',
    'label'=>'Autorización rectificación',
    'help' => 'Código de autorización de rectificación obtenido desde el SII <a href="#" onclick="get_codigo_reemplazo()">[solicitar código aquí]</a>',
    'check'=>'notempty',
]);
echo $f->end('Enviar rectificación');
