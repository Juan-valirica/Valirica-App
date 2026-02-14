<div class="top-row" style="display:flex; align-items:center; justify-content:space-between; width:100%; margin-bottom:30px;">
    <img src="<?= (substr($logo, 0, 1) === '/') ? $logo : '/' . $logo ?>" alt="Logo de la Empresa" class="company-logo" style="width: 100px; height: 100px; object-fit:cover; clip-path: circle(50% at 50% 50%); margin-right: 20px;">
    <div class="company-info">
        <h1 style="font-size: 32px; margin: 0;"><?= $empresa ?></h1>
        <p style="font-size: 20px; color: #666;">Tu guía cultural es <?= $nombre_cultura ?></p>
    </div>
</div>

    <img style="padding-top:30px; padding-bottom:30px; width:100%;" src="/uploads/Separador.png">

   <!-- Sección de Botones -->
<div class="botones-section" style="width: 100%;">
    <div style="display: flex; justify-content: space-around; align-items: center; width: 100%;">
        
            <!-- Cuadro del porcentaje de alineación -->
    <div style="background-color: #FFF; border-radius: 15px; padding: 20px; box-shadow: 0px 0px 10px rgba(0,0,0,0.1); min-width: 200px; text-align: center;">
        <h2 style="color:#FF7800; margin:0;">% de <?= htmlspecialchars($empleado['nombre_persona']) ?></h2>
        <p style="font-size: 45px; color:#004758; font-weight: bold; margin:10px 0;">
            <?= isset($alineacion_empleado) ? $alineacion_empleado : 'N/A' ?>%
        </p>
    </div>
        <!-- Botones existentes -->
        <div class="buttons-container" style="flex: 1; display: flex; justify-content: space-around;">
            <div class="button">
                <a href="ver_analisis.php?user_id=<?= $user_id ?>">
                    <img src="/uploads/Boton-proposito.png" alt="Icon 1">
                    <span style="color:#FF7800;">Propósito</br>& Valores</span>
                </a>
            </div>
            <div class="button">
                <a href="dimension_cultura_hofstede.php?user_id=<?= $user_id ?>">
                    <img src="/uploads/Boton-cultura.png" alt="Icon 2">
                    <span style="color:#FF7800;">Alineación</br>Cultural</span>
                </a>
            </div>
            <div class="button">
                <a href="rituales_cultura.php?user_id=<?= $user_id ?>">
                    <img src="/uploads/Boton-rituales.png" alt="Icon 3">
                    <span style="color:#FF7800;">Rituales</br>de Marca</span>
                </a>
            </div>
        </div>
    </div>
</div>
    <img style="padding-top:30px; padding-bottom:30px; width:100%;" src="/uploads/Separador.png">
