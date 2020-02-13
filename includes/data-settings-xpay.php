<?php
if (defined('PHP_SESSION_NONE') && session_id() == PHP_SESSION_NONE || session_id() == '') {
    session_start();
}
$countries = XpayLib::getCountries();
$op_countries = array();
foreach($countries as $c) {
    $op_countries[$c['code']] = $c['name'];
}
$xpay_currencies    = include 'data-currencies.php';
$log_url = home_url( '/wp-content/plugins/woocommerce-xpay/logs/index.php?token=' . md5($_SERVER['HTTP_HOST'].gmdate('Ymd').session_id()) );
return array(
    'enabled' => array(
        'title' => __( 'Activate', 'woocommerce-xpay' ),
        'type' => 'checkbox',
        'label' => __( 'Activar Xpay', 'woocommerce-xpay' ),
        'default' => 'yes',
    ),
    'title' => array(
        'title' => __( 'Title', 'woocommerce-xpay' ),
        'type' => 'text',
        'description' => __( 'Agregue el nombre a Xpay que se mostrará al cliente', 'woocommerce-xpay' ),
        'desc_tip' => true,
        'default' => __( 'Xpay', 'woocommerce-xpay' ),
    ),
    'description' => array(
        'title' => __( 'Descripción', 'woocommerce-xpay' ),
        'type' => 'textarea',
        'description' => __( 'Agregar una descripción a este método de pago', 'woocommerce-xpay' ),
        'default' => __( 'Pagar con Xpay', 'woocommerce-xpay' ),
    ),
    'client_id' => array(
        'title' => __( 'Xpay E-Mail', 'woocommerce-xpay' ),
        'type' => 'text',
        'description' => __( 'Ingresa el e-mail de su cuenta Xpay.', 'woocommerce-xpay' ),
        'default' => '',
    ),
    'client_secret' => array(
        'title' => __( 'Xpay Password', 'woocommerce-xpay' ),
        'type' => 'text',
        'description' => __( 'Ingresa la contraseña de su cuenta Xpay', 'woocommerce-xpay' ),
        'default' => '',
    ),
    'mp_completed' => array(
        'title' => __( 'Dejar pagos Aceptados en Completados', 'woocommerce-xpay' ),
        'type' => 'checkbox',
        'label' => __( 'Active', 'woocommerce-xpay' ),
        'default' => 'no',
        'description' => __( 'Cuando se aprueba el pago, el pedido en WooCommerce no permanecerá en Procesamiento sino en Completado.', 'woocommerce-xpay' ),
    ),
    'xpay_country' => array(
        'title'         => __( 'País donde opera su cuenta de Xpay', 'woocommerce-mercadoenvios' ),
        'type'          => 'select',
        'label'         => __( 'Selecciona el país donde opera su cuenta de xpay', 'woocommerce-mercadoenvios' ),
        'default'       => 'CO',
        'options'       => $op_countries,
    ),
    'convertion_option' => array(
        'title' => sprintf( __( 'Activar conversion de %1$s a %2$s', 'woocommerce-mercadoenvios' ), $currency_org, $currency_dst ),
        'type' => 'select',
        'label' => __( 'Activa el plugin convirtiendo los montos a la moneda de Xpay', 'woocommerce-mercadoenvios' ),
        'default' => '',
        'options'         => array(
            'off'    => __( 'Desactivar Modulo', 'woocommerce-mercadoenvios' ),
            'live-rates' => __( 'Usar la tasa de conversion de live-rates.com (No aplica a Bolivares)', 'woocommerce-mercadoenvios' ),
            'dicom' => __( 'Usar la tasa de conversion oficial (Solo aplica a USD/EUR <-> Bolivares)', 'woocommerce-mercadoenvios' ),
            'promedio' => __( 'Usar la tasa de conversion promedio (Solo aplica a USD/EUR <-> Bolivares)', 'woocommerce-mercadoenvios' ),
            'custom' => __( 'Usar una tasa de conversion Manual', 'woocommerce-mercadoenvios' ),
        ),
    ),
    'convertion_rate' => array(
        'title' => sprintf( __( 'Convertir usando Tasa Manual de %1$s a %2$s', 'woocommerce-mercadoenvios' ), $currency_org, $currency_dst ),
        'type' => 'text',
        'label' => __( 'Utilizar una tasa de conversion manual', 'woocommerce-mercadoenvios' ),
        'default' => '',
    ),
    'debug' => array(
        'title' => __( 'Debug Mode', 'woocommerce-xpay' ),
        'type' => 'checkbox',
        'label' => __( 'Enable Debug file in the directory', 'woocommerce-xpay').
            ' <a href="'.$log_url.'" target="_blank">/wp-content/plugins/woocommerce-xpay/logs/</a>.',
        'default' => 'yes',
    ),
);
