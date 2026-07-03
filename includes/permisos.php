<?php
// includes/permisos.php

function esAdmin()
{
    return isset($_SESSION['rol_nombre']) && $_SESSION['rol_nombre'] === 'admin';
}

function esSupervisor()
{
    return isset($_SESSION['rol_nombre']) && $_SESSION['rol_nombre'] === 'supervisor';
}

function esOperario()
{
    return isset($_SESSION['rol_nombre']) && $_SESSION['rol_nombre'] === 'operario';
}

function puedeModificar()
{
    return esAdmin() || esSupervisor();
}

function puedeSoloConsultar()
{
    return esOperario();
}

function protegerAdmin()
{
    if (!esAdmin()) {
        header('Location: dashboard.php');
        exit;
    }
}

function protegerModificacion()
{
    if (!puedeModificar()) {
        header('Location: dashboard.php');
        exit;
    }
}
