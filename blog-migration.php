<?php
/**
 * Blog Module — Migration & Seed
 * Crea tabla blog_posts e inserta artículos iniciales SEO-optimizados
 * Ejecutar una sola vez: https://app.valirica.com/blog-migration.php
 */
require_once 'config.php';

$errors = [];
$success = [];

// ─── 1. Crear tabla ────────────────────────────────────────────────────────
$createTable = "
CREATE TABLE IF NOT EXISTS `blog_posts` (
  `id`               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `slug`             VARCHAR(300)    NOT NULL,
  `title`            VARCHAR(600)    NOT NULL,
  `excerpt`          TEXT            NOT NULL,
  `content`          LONGTEXT        NOT NULL,
  `cover_gradient`   VARCHAR(200)    NOT NULL DEFAULT 'linear-gradient(135deg,#012133,#184656)',
  `cover_image`      VARCHAR(600)    DEFAULT NULL,
  `author_name`      VARCHAR(200)    NOT NULL DEFAULT 'Equipo Valírica',
  `author_title`     VARCHAR(200)    NOT NULL DEFAULT 'Especialistas en Cultura Organizacional',
  `author_avatar`    VARCHAR(600)    DEFAULT NULL,
  `category`         VARCHAR(120)    NOT NULL DEFAULT 'Cultura Organizacional',
  `tags`             VARCHAR(800)    DEFAULT NULL,
  `status`           ENUM('draft','published') NOT NULL DEFAULT 'draft',
  `featured`         TINYINT(1)      NOT NULL DEFAULT 0,
  `seo_title`        VARCHAR(600)    DEFAULT NULL,
  `seo_description`  VARCHAR(400)    DEFAULT NULL,
  `seo_keywords`     VARCHAR(600)    DEFAULT NULL,
  `reading_time`     TINYINT         NOT NULL DEFAULT 5,
  `view_count`       INT UNSIGNED    NOT NULL DEFAULT 0,
  `published_at`     DATETIME        DEFAULT NULL,
  `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_slug` (`slug`),
  KEY `idx_status_pub` (`status`, `published_at`),
  KEY `idx_category`   (`category`),
  KEY `idx_featured`   (`featured`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

if ($conn->query($createTable)) {
    $success[] = "✅ Tabla <code>blog_posts</code> creada (o ya existía).";
} else {
    $errors[] = "❌ Error creando tabla: " . $conn->error;
}

// ─── 2. Datos semilla ──────────────────────────────────────────────────────
$posts = [

  // ── POST 1 ──────────────────────────────────────────────────────────────
  [
    'slug'             => 'cultura-organizacional-ventaja-competitiva-sostenible',
    'title'            => 'Cultura Organizacional: La Única Ventaja Competitiva que No Puedes Copiar',
    'excerpt'          => 'Tu tecnología puede replicarse. Tu precio, también. Pero la cultura de tu empresa, la forma en que tu equipo piensa, decide y se relaciona, es imposible de copiar. Descubre por qué la cultura es el activo estratégico más valioso y cómo empezar a medirla hoy.',
    'cover_gradient'   => 'linear-gradient(135deg, #012133 0%, #023047 40%, #007a96 100%)',
    'author_name'      => 'Equipo Valírica',
    'author_title'     => 'Especialistas en Cultura Organizacional',
    'category'         => 'Cultura Organizacional',
    'tags'             => 'cultura organizacional,ventaja competitiva,liderazgo empresarial,retención de talento,transformación cultural',
    'status'           => 'published',
    'featured'         => 1,
    'seo_title'        => 'Cultura Organizacional: La Ventaja Competitiva que No se Puede Copiar | Valírica',
    'seo_description'  => 'Descubre por qué la cultura organizacional es la ventaja competitiva más sostenible de tu empresa y cómo medirla con datos reales para activarla.',
    'seo_keywords'     => 'cultura organizacional, ventaja competitiva, cómo medir cultura empresarial, retención talento, transformación cultural',
    'reading_time'     => 9,
    'published_at'     => '2026-02-10 10:00:00',
    'content'          => '
<h2>El problema con las ventajas competitivas tradicionales</h2>
<p>Durante décadas, las empresas compitieron en tres ejes: precio, producto y distribución. Pero en un mundo donde cualquier startup puede construir el mismo software en seis meses, donde los costes de manufactura se igualan globalmente y donde la logística se ha commoditizado, esas ventajas ya no son suficientes.</p>
<p>Lo que sí permanece, lo que crece con el tiempo y se vuelve más difícil de imitar cuanto más se cultiva, es la cultura organizacional.</p>
<blockquote><strong>"La cultura se come a la estrategia en el desayuno."</strong><br><em>— Peter Drucker</em></blockquote>
<p>Esta frase resume algo que los líderes más avezados conocen de primera mano: puedes tener el mejor plan de negocio del mundo, pero si tu gente no cree en él, si no comparte los valores que lo sustentan, si no confía en el liderazgo que lo dirige, ese plan fracasará.</p>

<h2>¿Qué es exactamente la cultura organizacional?</h2>
<p>La cultura organizacional es el conjunto de valores, creencias, comportamientos y normas que definen cómo funciona una organización desde adentro. Es "la forma en que hacemos las cosas aquí", pero también es algo más profundo: es el marco invisible que guía las decisiones cuando nadie está mirando.</p>
<p>Se manifiesta en:</p>
<ul>
  <li><strong>Cómo se toman decisiones:</strong> ¿se consulta al equipo o decide el jefe?</li>
  <li><strong>Cómo se gestiona el error:</strong> ¿se castiga o se aprende?</li>
  <li><strong>Cómo se reconoce el logro:</strong> ¿de forma individual o colectiva?</li>
  <li><strong>Cómo se comunica:</strong> ¿con transparencia o en silos?</li>
  <li><strong>Qué se valora realmente:</strong> ¿el resultado a corto plazo o el crecimiento sostenible?</li>
</ul>

<h2>Por qué la cultura no se puede copiar</h2>
<p>Cuando Amazon lanzó su famoso documento "Leadership Principles", muchas empresas intentaron replicar sus principios literalmente. El resultado, en la mayoría de los casos, fue decoración de paredes. Los 14 principios de Amazon funcionan en Amazon porque fueron construidos a lo largo de años, internalizados por miles de personas, reforzados con sistemas de selección, evaluación y promoción coherentes. No son un eslogan; son un sistema vivo.</p>
<p>Esto ilustra la razón fundamental por la que la cultura es inimitable: <strong>es el resultado de miles de micro-decisiones tomadas a lo largo del tiempo</strong>. No existe un manual de instrucciones. No se puede instalar con una consultoría de tres meses. Se construye, o se destruye, día a día.</p>

<h2>Los números que importan</h2>
<p>No hablemos solo de filosofía. Los datos son contundentes:</p>
<ul>
  <li>Las empresas con culturas fuertes y alineadas tienen <strong>hasta un 72% más de engagement</strong> en sus empleados (Gallup, 2024).</li>
  <li>Los equipos altamente comprometidos son <strong>21% más productivos</strong> que los que no lo están.</li>
  <li>El coste de reemplazar a un empleado puede llegar al <strong>200% de su salario anual</strong> cuando se cuentan la selección, formación y pérdida de productividad.</li>
  <li>Las organizaciones con culturas inclusivas y bien definidas reportan <strong>3 veces más innovación</strong> que el promedio de su sector.</li>
</ul>

<h2>Cómo se mide la cultura organizacional</h2>
<p>El gran obstáculo para muchos líderes es que la cultura parece intangible. "¿Cómo mido algo que no puedo ver?" La respuesta está en medir sus manifestaciones: comportamientos, percepciones, alineación de valores y patrones de decisión.</p>
<p>En Valírica, utilizamos un enfoque multidimensional que combina:</p>
<ul>
  <li><strong>Modelos de personalidad (DISC):</strong> Para entender la diversidad de perfiles y sus dinámicas.</li>
  <li><strong>Dimensiones culturales (Hofstede):</strong> Para mapear distancia al poder, individualismo, tolerancia a la incertidumbre y otros ejes clave.</li>
  <li><strong>Análisis de propósito y valores:</strong> Para detectar la brecha entre los valores declarados y los valores vividos.</li>
  <li><strong>Indicadores de comportamiento:</strong> Asistencia, comunicación, productividad y alineación con objetivos.</li>
</ul>

<h2>Los tres niveles de cultura (modelo de Schein)</h2>
<p>El psicólogo organizacional Edgar Schein propone entender la cultura en tres capas:</p>
<ol>
  <li><strong>Artefactos (lo visible):</strong> El espacio de trabajo, el lenguaje, las reuniones, los rituales, el organigrama.</li>
  <li><strong>Valores esposados (lo declarado):</strong> La misión, visión, los valores en la web, los mensajes del liderazgo.</li>
  <li><strong>Supuestos básicos (lo profundo):</strong> Las creencias inconscientes que realmente guían el comportamiento.</li>
</ol>
<p>El gran peligro es cuando existe una brecha enorme entre el nivel 2 y el nivel 3. Cuando lo que la empresa dice que valora (innovación, autonomía, bienestar) choca con lo que realmente recompensa (horas extras, conformidad, resultados a corto plazo), se genera desconfianza y cinismo.</p>

<h2>Cómo empezar a transformar tu cultura hoy</h2>
<p>La transformación cultural no empieza con un taller de dos días ni con un nuevo conjunto de valores impresos en la sala de reuniones. Empieza con datos y con honestidad.</p>
<ol>
  <li><strong>Mide antes de actuar:</strong> Antes de cambiar nada, entiende dónde estás. Aplica diagnósticos de cultura, recoge datos de engagement, analiza los perfiles de tu equipo.</li>
  <li><strong>Identifica las brechas:</strong> ¿Cuáles son los valores que proclamas pero no practicas? ¿Dónde hay inconsistencia entre lo que el liderazgo pide y lo que el sistema recompensa?</li>
  <li><strong>Involucra a todos los niveles:</strong> La cultura no la construye solo el CEO. La construyen los mandos medios en sus reuniones de equipo, los compañeros en su trato diario, los procesos de RRHH en sus decisiones.</li>
  <li><strong>Mide de forma continua:</strong> La cultura es dinámica. Necesita monitorización constante, no solo diagnósticos anuales.</li>
  <li><strong>Celebra los comportamientos correctos:</strong> Lo que se reconoce se repite. Define con claridad qué comportamientos encarnan tu cultura y hazlos visibles.</li>
</ol>

<h2>Conclusión: La cultura es una elección estratégica</h2>
<p>Toda empresa tiene cultura, la haya diseñado o no. La diferencia entre las organizaciones que lideran sus sectores y las que sobreviven a duras penas no es siempre la tecnología, el capital o el talento individual. Con frecuencia, es la capacidad de construir un entorno donde las personas quieren dar lo mejor de sí mismas.</p>
<p>La buena noticia es que la cultura puede medirse, puede dirigirse y puede transformarse. No de la noche a la mañana, pero sí con constancia, con datos y con el compromiso del liderazgo.</p>
<p>En Valírica, hemos construido la plataforma para hacerlo posible: medir tu cultura hoy, identificar las brechas y activar el cambio con evidencia.</p>

<div class="blog-faq">
<h2>Preguntas frecuentes sobre cultura organizacional</h2>
<div class="faq-item">
  <h3>¿Cuánto tiempo lleva cambiar la cultura de una empresa?</h3>
  <p>Transformar la cultura de una organización requiere entre 2 y 5 años de trabajo consistente. Los cambios superficiales (nuevos valores en la pared, talleres puntuales) se notan antes, pero los cambios profundos en comportamientos y creencias necesitan tiempo, repetición y coherencia en todos los niveles del liderazgo.</p>
</div>
<div class="faq-item">
  <h3>¿Cómo sé si mi empresa tiene una cultura tóxica?</h3>
  <p>Las señales más claras incluyen: alta rotación de personal (especialmente en los primeros 12 meses), bajo engagement en encuestas, reuniones donde nadie habla libremente, comportamientos diferentes en función de si el jefe está presente, y desconexión entre los valores declarados y las decisiones reales.</p>
</div>
<div class="faq-item">
  <h3>¿Puede una empresa pequeña tener cultura organizacional?</h3>
  <p>Absolutamente. De hecho, las empresas pequeñas tienen una ventaja: la cultura se construye más rápido y es más fácil de cambiar. En un equipo de 10 personas, el fundador tiene una influencia directa y diaria en cómo se vive la cultura. El riesgo es no gestionarla conscientemente y dejar que se forme por accidente.</p>
</div>
</div>
',
  ],

  // ── POST 2 ──────────────────────────────────────────────────────────────
  [
    'slug'             => 'disc-equipos-alto-rendimiento-guia-lideres',
    'title'            => 'DISC en Equipos de Alto Rendimiento: Guía Práctica para Líderes',
    'excerpt'          => 'El modelo DISC revela por qué personas igualmente competentes colaboran de formas tan distintas. Aprende a usar DISC para comunicarte mejor con cada perfil, gestionar conflictos y construir equipos complementarios que se potencian mutuamente.',
    'cover_gradient'   => 'linear-gradient(135deg, #012133 0%, #1a3a4a 40%, #EF7F1B 100%)',
    'author_name'      => 'Equipo Valírica',
    'author_title'     => 'Especialistas en Cultura Organizacional',
    'category'         => 'Liderazgo y Equipos',
    'tags'             => 'DISC,equipos alto rendimiento,liderazgo,comunicación,gestión de equipos,psicología organizacional',
    'status'           => 'published',
    'featured'         => 0,
    'seo_title'        => 'Modelo DISC para Equipos: Guía Práctica para Líderes | Valírica',
    'seo_description'  => 'Aprende a usar el modelo DISC para mejorar la comunicación, gestionar conflictos y construir equipos complementarios de alto rendimiento.',
    'seo_keywords'     => 'modelo DISC, DISC equipos, perfiles DISC liderazgo, cómo usar DISC en empresa, equipos alto rendimiento',
    'reading_time'     => 10,
    'published_at'     => '2026-02-17 10:00:00',
    'content'          => '
<h2>¿Qué es el modelo DISC?</h2>
<p>DISC es un modelo de evaluación conductual basado en la teoría del psicólogo William Moulton Marston (1928). No mide inteligencia, habilidades técnicas ni valores, sino el <strong>estilo de comportamiento observable</strong> de una persona: cómo se comunica, cómo responde a los retos, cómo prefiere trabajar y cómo reacciona bajo presión.</p>
<p>El nombre es un acrónimo de sus cuatro dimensiones:</p>
<ul>
  <li><strong>D – Dominancia:</strong> Orientación a resultados, directo, competitivo, decisivo.</li>
  <li><strong>I – Influencia:</strong> Orientación a personas, entusiasta, persuasivo, sociable.</li>
  <li><strong>S – Estabilidad:</strong> Orientación a la consistencia, paciente, leal, metódico.</li>
  <li><strong>C – Cumplimiento:</strong> Orientación a la precisión, analítico, sistemático, orientado a la calidad.</li>
</ul>
<p>Importante: todas las personas tenemos los cuatro estilos en diferentes proporciones. No hay perfiles "buenos" ni "malos". Cada uno aporta fortalezas únicas al equipo.</p>

<h2>Por qué DISC transforma la dinámica de equipos</h2>
<p>El conflicto más común en los equipos no surge de malas intenciones, sino de <strong>estilos de trabajo incomprendidos</strong>. Un perfil D que necesita rapidez y decisión puede percibir a un perfil C como lento y excesivamente cauteloso. Un perfil S que valora la estabilidad puede percibir a un I como superficial o desenfocado.</p>
<p>Cuando los miembros del equipo entienden estos estilos, cambian el juicio por la comprensión. Y con comprensión, la colaboración se vuelve mucho más efectiva.</p>

<h2>Los 4 perfiles DISC en profundidad</h2>

<h3>Perfil D — Dominancia</h3>
<p>Las personas con alta D son directas, orientadas a resultados y toman decisiones rápidas. Prosperan en entornos de reto y no temen el conflicto. Su fortaleza es la capacidad de mover las cosas hacia adelante.</p>
<p><strong>Cómo comunicarte con un D:</strong> Ve al grano. Presenta opciones con pros y contras claros. Deja que decidan. Evita largas explicaciones de proceso; les interesa el resultado.</p>
<p><strong>Su talón de Aquiles:</strong> Pueden pasar por alto los detalles importantes y las implicaciones emocionales de sus decisiones.</p>

<h3>Perfil I — Influencia</h3>
<p>Los perfiles I son entusiastas, creativos y socialmente hábiles. Generan energía positiva en el equipo y son excelentes comunicadores. Su fortaleza está en motivar, conectar y vender ideas.</p>
<p><strong>Cómo comunicarte con un I:</strong> Muestra entusiasmo. Permíteles hablar y compartir ideas. Reconoce su contribución públicamente. Evita presentarles muchos datos técnicos sin contexto emocional.</p>
<p><strong>Su talón de Aquiles:</strong> Pueden ser impulsivos y perder el seguimiento de detalles o compromisos.</p>

<h3>Perfil S — Estabilidad</h3>
<p>Las personas S son el corazón del equipo. Pacientes, leales y excelentes escuchando, crean un ambiente de confianza y cohesión. Son los que "mantienen el barco" cuando las cosas se ponen difíciles.</p>
<p><strong>Cómo comunicarte con un S:</strong> Sé consistente y predecible. Explica el "por qué" detrás de los cambios. Dale tiempo para procesar antes de pedir una respuesta. Evita los cambios abruptos sin preparación.</p>
<p><strong>Su talón de Aquiles:</strong> Pueden resistirse al cambio y evitar conflictos necesarios.</p>

<h3>Perfil C — Cumplimiento</h3>
<p>Los perfiles C son analíticos, meticulosos y tienen altos estándares de calidad. Son los que hacen las preguntas difíciles, revisan los detalles y aseguran que el trabajo se haga bien. Son esenciales para la precisión y la calidad.</p>
<p><strong>Cómo comunicarte con un C:</strong> Proporciona datos, evidencias y lógica. Deja que analicen antes de decidir. Respeta sus preguntas técnicas. Evita presionarles para una respuesta rápida sin información suficiente.</p>
<p><strong>Su talón de Aquiles:</strong> Pueden caer en el análisis-parálisis y ser perfeccionistas hasta el punto de retrasar la acción.</p>

<h2>Cómo construir equipos complementarios con DISC</h2>
<p>La magia del DISC en los equipos ocurre cuando se usa para construir equipos intencionalmente complementarios, no equipos donde todos piensan igual.</p>
<p>Un equipo formado solo por perfiles D tomará decisiones rápidas pero cometerá muchos errores de detalle. Un equipo de solo C producirá análisis impecables pero nunca decidirá. La combinación inteligente de estilos crea equipos que son tanto rápidos como precisos, tanto orientados a resultados como a relaciones.</p>
<p>Algunas combinaciones poderosas:</p>
<ul>
  <li><strong>D + C:</strong> El D impulsa, el C verifica. Juntos, son velocidad con calidad.</li>
  <li><strong>I + S:</strong> El I conecta externamente, el S mantiene la cohesión interna.</li>
  <li><strong>D + S:</strong> El D marca la dirección, el S sostiene el ritmo y cuida al equipo.</li>
  <li><strong>I + C:</strong> El I genera ideas, el C las evalúa críticamente.</li>
</ul>

<h2>DISC y gestión de conflictos</h2>
<p>La mayoría de los conflictos en equipos son conflictos de estilo disfrazados de desacuerdos de contenido. Entender esto cambia completamente cómo los gestionas.</p>
<p>Cuando un D y un S están en conflicto, el D quiere resolver rápido y seguir adelante; el S necesita tiempo para procesar y sentirse escuchado. El error del líder es darle la razón a uno sin entender que ambos tienen necesidades legítimas.</p>
<p>El DISC te da el lenguaje para mediar: "Entiendo que necesitas avanzar (D), y también entiendo que necesitas sentirte seguro con la decisión (S). ¿Qué información mínima necesitamos para poder decidir hoy?"</p>

<h2>Cómo usar DISC como líder de equipo</h2>
<ol>
  <li><strong>Evalúa a tu equipo:</strong> Comienza con una evaluación DISC para todos los miembros.</li>
  <li><strong>Comparte los resultados:</strong> La transparencia es clave. Que cada persona conozca su perfil y el de sus compañeros.</li>
  <li><strong>Facilita conversaciones:</strong> Usa los perfiles para hablar sobre cómo colaborar mejor, no para etiquetar o limitar.</li>
  <li><strong>Adapta tu comunicación:</strong> El mejor líder no comunica de la forma que más le gusta a él, sino de la forma que mejor funciona para cada persona.</li>
  <li><strong>Revisita periódicamente:</strong> Los perfiles DISC pueden variar con el contexto y el tiempo. Haz evaluaciones regulares.</li>
</ol>

<div class="blog-faq">
<h2>Preguntas frecuentes sobre DISC</h2>
<div class="faq-item">
  <h3>¿Es el DISC una prueba de personalidad confiable?</h3>
  <p>El DISC es una herramienta de comportamiento observable, no un test de personalidad profunda como el MBTI o el Big Five. Tiene alta fiabilidad test-retest y es muy útil como herramienta práctica de comunicación y gestión de equipos. No debe usarse como único criterio para decisiones de selección o promoción.</p>
</div>
<div class="faq-item">
  <h3>¿Cuánto tiempo tarda en completarse una evaluación DISC?</h3>
  <p>Una evaluación DISC bien diseñada tarda entre 15 y 25 minutos en completarse. En Valírica, nuestro formulario DISC adaptativo incluye además dimensiones culturales de Hofstede para un análisis más completo.</p>
</div>
<div class="faq-item">
  <h3>¿Puede cambiar mi perfil DISC con el tiempo?</h3>
  <p>Sí. El perfil DISC refleja comportamientos, que pueden adaptarse según el contexto, el rol y las experiencias de vida. Es habitual que el perfil muestre variaciones menores entre evaluaciones, aunque el estilo dominante suele mantenerse estable en el tiempo.</p>
</div>
</div>
',
  ],

  // ── POST 3 ──────────────────────────────────────────────────────────────
  [
    'slug'             => '7-metricas-medir-salud-cultura-organizacional',
    'title'            => '7 Métricas para Medir la Salud de tu Cultura Organizacional (con Datos Reales)',
    'excerpt'          => '"Lo que no se mide, no se puede mejorar." Si quieres transformar tu cultura, necesitas saber dónde estás hoy. Estas 7 métricas te darán una fotografía fiel de la salud cultural de tu empresa, más allá de las encuestas de clima anuales.',
    'cover_gradient'   => 'linear-gradient(135deg, #011929 0%, #034461 50%, #2e7d9e 100%)',
    'author_name'      => 'Equipo Valírica',
    'author_title'     => 'Especialistas en Cultura Organizacional',
    'category'         => 'Gestión del Talento',
    'tags'             => 'métricas cultura,employee engagement,KPIs RRHH,salud organizacional,people analytics',
    'status'           => 'published',
    'featured'         => 0,
    'seo_title'        => '7 Métricas Clave para Medir la Cultura Organizacional | Valírica',
    'seo_description'  => 'Aprende a medir la salud de tu cultura organizacional con 7 métricas basadas en datos: eNPS, rotación, engagement, absentismo y más.',
    'seo_keywords'     => 'cómo medir cultura organizacional, métricas cultura empresarial, KPIs recursos humanos, employee engagement métricas',
    'reading_time'     => 8,
    'published_at'     => '2026-02-24 10:00:00',
    'content'          => '
<h2>Por qué necesitas métricas de cultura (no solo encuestas de clima)</h2>
<p>Durante décadas, la herramienta estándar para "medir" la cultura fue la encuesta de clima laboral anual. El problema es bien conocido: llega tarde (detecta problemas que ya tienen meses), es poco frecuente, y raramente se traduce en acción concreta antes de que llegue la siguiente edición.</p>
<p>Las organizaciones que realmente gestionan su cultura no esperan al diagnóstico anual. Monitorizan un conjunto de <strong>indicadores continuos</strong> que les permiten detectar señales tempranas y actuar antes de que los problemas se conviertan en crisis.</p>
<p>Estas son las 7 métricas que todo líder debería tener en su dashboard de cultura.</p>

<h2>1. eNPS — Employee Net Promoter Score</h2>
<p>El eNPS adapta la famosa pregunta del NPS de clientes al contexto interno: <em>"¿Con qué probabilidad recomendarías esta empresa como lugar para trabajar?"</em> (escala 0–10).</p>
<p>Los empleados que puntúan 9–10 son <strong>promotores</strong>; los que puntúan 7–8 son <strong>pasivos</strong>; los que puntúan 0–6 son <strong>detractores</strong>.</p>
<p><strong>Cálculo:</strong> eNPS = % Promotores − % Detractores</p>
<p><strong>Benchmarks:</strong> Menos de 0 es una señal de alerta. Entre 10–30 es aceptable. Más de 50 es excelente. Las empresas en el percentil 75 de su sector suelen tener eNPS superiores a 40.</p>
<p><strong>Clave:</strong> Mídelo trimestral, no anualmente. Los cambios rápidos son señal de que algo importante está pasando.</p>

<h2>2. Tasa de rotación voluntaria</h2>
<p>La rotación voluntaria (cuando el empleado elige irse) es uno de los indicadores más honestos de la cultura. Las personas no suelen dejar empresas; dejan culturas, o más concretamente, dejan líderes y entornos que no se alinean con sus valores.</p>
<p><strong>Cálculo:</strong> (Nº de bajas voluntarias en el período / Plantilla media) × 100</p>
<p><strong>Benchmarks:</strong> La tasa "saludable" varía por sector, pero como referencia general: menos del 10% anual se considera bajo, 10–20% moderado, y más del 20% es una señal de problema sistémico.</p>
<p><strong>Dato importante:</strong> Analiza la rotación por nivel. Si la rotación es alta en mandos medios, es una señal especialmente preocupante, ya que son el mayor transmisor de cultura en la organización.</p>

<h2>3. Índice de compromiso (Engagement Score)</h2>
<p>El engagement va más allá de la satisfacción. Un empleado satisfecho no genera problemas. Un empleado comprometido da lo mejor de sí mismo, aunque nadie lo esté mirando. La diferencia entre ambos es enorme para el rendimiento.</p>
<p>Para medirlo, puedes usar encuestas cortas y frecuentes (pulse surveys de 5–10 preguntas) que evalúen:</p>
<ul>
  <li>Claridad en el propósito del rol</li>
  <li>Conexión con los objetivos del equipo</li>
  <li>Percepción de desarrollo profesional</li>
  <li>Relación con el manager directo</li>
  <li>Sentido de reconocimiento</li>
</ul>
<p><strong>Frecuencia recomendada:</strong> Mensual o bimestral. Más frecuente genera fatiga; menos frecuente pierde actualidad.</p>

<h2>4. Absentismo no planificado</h2>
<p>El absentismo, especialmente el no planificado (bajas inesperadas, días de "enfermedad" recurrentes), es un termómetro cultural muy preciso. Las investigaciones muestran que el absentismo no planificado está altamente correlacionado con el estrés laboral, la toxicidad del ambiente y la falta de propósito.</p>
<p><strong>Cálculo:</strong> (Días de ausencia no planificada / Días laborables totales) × 100</p>
<p><strong>Benchmark:</strong> Una tasa del 2–3% se considera normal. Por encima del 5% es una señal de alerta cultural.</p>

<h2>5. Tiempo para cubrir vacantes</h2>
<p>Este indicador mide cuánto tiempo pasa desde que se abre una vacante hasta que se incorpora la nueva persona. Aunque parece un KPI de selección, refleja también la cultura: las empresas con culturas atractivas reciben más y mejores candidatos, y cierran procesos más rápido.</p>
<p>Si tu tiempo de cobertura aumenta, puede ser señal de que tu employer brand (tu reputación como empleador) se está deteriorando.</p>

<h2>6. Tasa de promoción interna</h2>
<p>Las empresas que promueven desde dentro envían un mensaje cultural poderoso: aquí el crecimiento es posible y el talento se reconoce. Una alta tasa de promoción interna está asociada con mayor engagement, mayor permanencia y mejor transmisión de cultura.</p>
<p><strong>Cálculo:</strong> (Vacantes cubiertas internamente / Total de vacantes) × 100</p>
<p><strong>Benchmark saludable:</strong> Entre el 30% y el 60% de las posiciones de liderazgo cubiertas internamente.</p>

<h2>7. Alineación de valores (Values Alignment Score)</h2>
<p>Esta es quizás la métrica más sofisticada y la más ignorada. Mide hasta qué punto los empleados perciben que los valores declarados de la empresa se reflejan en las decisiones y comportamientos reales del día a día.</p>
<p>Se puede medir con preguntas como: <em>"¿En qué medida crees que las decisiones de liderazgo reflejan los valores de la empresa?"</em> o <em>"¿Ves coherencia entre lo que la empresa dice que valora y cómo actúa en situaciones difíciles?"</em></p>
<p>Una alta brecha entre valores declarados y valores percibidos es la fuente número uno de cinismo organizacional, que destruye la confianza y el compromiso.</p>

<h2>Cómo integrar estas métricas en tu gestión</h2>
<p>No se trata de medirlo todo a la vez. Empieza con 2 o 3 indicadores, establece baselines, y crea rutinas de revisión. Lo importante es convertir los datos en conversaciones y las conversaciones en acciones.</p>
<p>En Valírica, centralizamos estas métricas en un dashboard que permite a los líderes ver la evolución de su cultura en tiempo real, detectar tendencias y actuar con anticipación.</p>

<div class="blog-faq">
<h2>Preguntas frecuentes sobre métricas de cultura</h2>
<div class="faq-item">
  <h3>¿Con qué frecuencia debo medir la cultura de mi empresa?</h3>
  <p>Para métricas cuantitativas como rotación o absentismo, el seguimiento mensual es ideal. Para encuestas de engagement o eNPS, lo trimestral es suficiente para detectar tendencias sin generar fatiga en el equipo. El análisis más profundo (valores, propósito, DISC) puede hacerse semestral o anualmente.</p>
</div>
<div class="faq-item">
  <h3>¿Cuál es la métrica más importante de cultura organizacional?</h3>
  <p>Si tienes que elegir una, el eNPS (Employee Net Promoter Score) es el indicador más fácil de calcular, más universalmente comparado y más accionable. Sin embargo, ninguna métrica aislada da el cuadro completo; la cultura se ve en la combinación de indicadores.</p>
</div>
</div>
',
  ],

  // ── POST 4 ──────────────────────────────────────────────────────────────
  [
    'slug'             => 'valores-corporativos-de-la-pared-a-la-practica',
    'title'            => 'Valores Corporativos: Cómo Pasar de la Pared a la Práctica Diaria',
    'excerpt'          => 'El 78% de los empleados no sabe describir los valores de su empresa más allá de los carteles en la sala de reuniones. Los valores no son decoración; son el sistema operativo de la cultura. Aprende a hacerlos reales.',
    'cover_gradient'   => 'linear-gradient(135deg, #012133 0%, #2a1a0a 60%, #8a4709 100%)',
    'author_name'      => 'Equipo Valírica',
    'author_title'     => 'Especialistas en Cultura Organizacional',
    'category'         => 'Cultura Organizacional',
    'tags'             => 'valores corporativos,cultura organizacional,liderazgo,transformación cultural,RRHH',
    'status'           => 'published',
    'featured'         => 0,
    'seo_title'        => 'Valores Corporativos Reales: De la Declaración a la Práctica | Valírica',
    'seo_description'  => 'Aprende cómo transformar los valores corporativos en comportamientos reales. Estrategias para que los valores sean el sistema operativo de tu cultura, no solo decoración.',
    'seo_keywords'     => 'valores corporativos empresa, cómo activar valores, cultura organizacional valores, valores declarados vs vividos',
    'reading_time'     => 7,
    'published_at'     => '2026-03-03 10:00:00',
    'content'          => '
<h2>El problema de los valores como decoración</h2>
<p>Entra a las oficinas de casi cualquier empresa mediana o grande y encontrarás, con toda probabilidad, un cartel en la entrada, una presentación de onboarding, o una página en la web que enuncia sus valores. Palabras como <em>integridad</em>, <em>innovación</em>, <em>trabajo en equipo</em>, <em>excelencia</em> aparecen en la mayoría.</p>
<p>Y sin embargo, pregunta a cualquier empleado con un año de antigüedad cómo se viven esos valores en el día a día, y la respuesta suele ser una sonrisa incómoda o un silencio elocuente.</p>
<p>Esto no es un problema de valores equivocados. Es un problema de <strong>activación</strong>. Los valores sin práctica son simplemente buenas intenciones que generan desconfianza cuando no se cumplen.</p>

<h2>¿Qué hace que un valor sea "real"?</h2>
<p>Un valor corporativo es real cuando cumple tres condiciones:</p>
<ol>
  <li><strong>Se puede observar:</strong> Existe un comportamiento específico que lo encarna. "Integridad" se hace real cuando alguien rechaza un atajo poco ético aunque nadie esté mirando.</li>
  <li><strong>Se reconoce:</strong> La organización celebra y visibiliza los comportamientos que lo reflejan.</li>
  <li><strong>Tiene consecuencias:</strong> Violar el valor tiene coste real, no solo retórico. Y vivirlo tiene beneficios reales, no solo morales.</li>
</ol>
<p>Cuando estos tres elementos están presentes, el valor deja de ser un concepto y se convierte en parte del ADN del equipo.</p>

<h2>El proceso de construcción de valores auténticos</h2>
<h3>Paso 1: Descubrir, no inventar</h3>
<p>Los mejores valores no se construyen en un retiro de dos días de liderazgo. Se descubren mirando qué es lo que ya existe en la organización, qué comportamientos son los que realmente se celebran, qué decisiones difíciles se han tomado de formas que el equipo respeta.</p>
<p>Pregúntate: <em>¿Qué es lo que hacemos cuando nadie nos está mirando? ¿Qué comportamientos admiramos en los compañeros más respetados? ¿Qué no haríamos nunca, aunque nos costara negocio?</em> Las respuestas te revelan los valores reales de tu organización.</p>

<h3>Paso 2: Traducirlos a comportamientos concretos</h3>
<p>Cada valor debe tener al menos 3 comportamientos observables asociados. Esto es lo que diferencia un valor vivo de uno decorativo.</p>
<p>Por ejemplo:</p>
<ul>
  <li><strong>Valor: "Transparencia"</strong>
    <ul>
      <li>Comportamiento: Compartimos los números reales del negocio con todo el equipo mensualmente.</li>
      <li>Comportamiento: Cuando cometemos un error, lo comunicamos proactivamente a los afectados antes de que lo descubran.</li>
      <li>Comportamiento: En las reuniones de liderazgo, cada decisión importante se documenta con el "por qué".</li>
    </ul>
  </li>
</ul>

<h3>Paso 3: Integrarlos en los sistemas de RRHH</h3>
<p>Los valores se vuelven reales cuando se integran en los procesos que más importan a las personas: selección, onboarding, evaluación del desempeño y promoción.</p>
<ul>
  <li><strong>Selección:</strong> Las entrevistas incluyen preguntas de valores, no solo de competencias técnicas.</li>
  <li><strong>Onboarding:</strong> Los nuevos empleados entienden los valores a través de historias reales, no de slides.</li>
  <li><strong>Evaluación:</strong> El desempeño se mide no solo por resultados, sino por cómo se consiguieron.</li>
  <li><strong>Promoción:</strong> Los líderes son quienes mejor encarnan los valores, no solo quienes generan más negocio.</li>
</ul>

<h3>Paso 4: El liderazgo como espejo</h3>
<p>No hay nada que destruya más rápido una cultura de valores que ver a un líder incumplirlos sin consecuencias. El equipo no escucha lo que el liderazgo dice; observa lo que el liderazgo hace.</p>
<p>Si la empresa declara que valora el equilibrio vida-trabajo pero el CEO envía correos a las 11 de la noche esperando respuesta inmediata, el mensaje real es que el equilibrio vida-trabajo es para quien puede permitírselo.</p>
<p>La coherencia del liderazgo es el test definitivo de autenticidad de los valores.</p>

<h3>Paso 5: Medir la alineación</h3>
<p>Periódicamente, mide cuán alineados están los valores declarados con los valores percibidos. Las encuestas de cultura pueden incluir preguntas como: <em>"¿En qué medida crees que [valor X] se vive realmente en nuestra empresa?"</em> La brecha entre lo que se dice y lo que se percibe es tu brújula de acción.</p>

<h2>Los errores más comunes en la implementación de valores</h2>
<ul>
  <li><strong>Valores diseñados por el liderazgo, sin el equipo:</strong> Los valores impuestos generan cumplimiento, no compromiso.</li>
  <li><strong>Demasiados valores:</strong> Si tienes más de 5 valores, nadie los recordará. La fuerza está en la claridad.</li>
  <li><strong>Sin consecuencias por incumplimiento:</strong> Un valor sin consecuencia es una aspiración.</li>
  <li><strong>Celebrar solo los resultados, no los comportamientos:</strong> Si solo reconoces quien vende más, no a quien lo hace con más integridad, estás enviando un mensaje claro sobre lo que realmente valoras.</li>
</ul>

<div class="blog-faq">
<h2>Preguntas frecuentes sobre valores corporativos</h2>
<div class="faq-item">
  <h3>¿Cuántos valores debe tener una empresa?</h3>
  <p>Lo ideal es entre 3 y 5 valores. Con menos, pueden no cubrir las dimensiones más importantes. Con más, se vuelven difíciles de recordar y de integrar en la práctica diaria. La calidad y la claridad son más importantes que la cantidad.</p>
</div>
<div class="faq-item">
  <h3>¿Con qué frecuencia se deben revisar los valores de una empresa?</h3>
  <p>Los valores fundamentales no deben cambiar frecuentemente; si lo hacen, es señal de que no eran verdaderos valores sino aspiraciones. Sin embargo, cada 3–5 años, o en momentos de transformación significativa (fusión, cambio de estrategia, crecimiento acelerado), tiene sentido revisar si siguen siendo representativos de quiénes somos y hacia dónde vamos.</p>
</div>
</div>
',
  ],

  // ── POST 5 ──────────────────────────────────────────────────────────────
  [
    'slug'             => 'modelo-hofstede-dimensiones-culturales-equipos',
    'title'            => 'El Modelo Hofstede: Cómo las Dimensiones Culturales Determinan tu Equipo',
    'excerpt'          => 'Geert Hofstede estudió 70 países y encontró patrones culturales que explican por qué equipos de diferentes orígenes trabajan de forma tan distinta. Entiende las 6 dimensiones y aplícalas para gestionar mejor a tu equipo, especialmente si es multicultural.',
    'cover_gradient'   => 'linear-gradient(135deg, #012133 0%, #103340 50%, #205869 100%)',
    'author_name'      => 'Equipo Valírica',
    'author_title'     => 'Especialistas en Cultura Organizacional',
    'category'         => 'Recursos Humanos',
    'tags'             => 'modelo Hofstede,dimensiones culturales,equipos multiculturales,diversidad cultural,gestión intercultural',
    'status'           => 'published',
    'featured'         => 0,
    'seo_title'        => 'Modelo Hofstede: Las 6 Dimensiones Culturales en Equipos | Valírica',
    'seo_description'  => 'Conoce las 6 dimensiones del modelo Hofstede y cómo aplicarlas para gestionar equipos multiculturales, mejorar la comunicación y reducir conflictos culturales.',
    'seo_keywords'     => 'modelo Hofstede, dimensiones culturales Hofstede, equipos multiculturales, gestión intercultural, distancia al poder',
    'reading_time'     => 11,
    'published_at'     => '2026-03-10 10:00:00',
    'content'          => '
<h2>¿Quién fue Geert Hofstede?</h2>
<p>Geert Hofstede (1928–2020) fue un psicólogo social neerlandés que realizó uno de los estudios más amplios de la historia sobre diferencias culturales en el mundo organizacional. Trabajando con datos de más de 100.000 empleados de IBM en 70 países durante la década de 1970, identificó dimensiones culturales que explicaban diferencias sistemáticas en valores, actitudes y comportamientos.</p>
<p>Su obra más influyente, "Culture's Consequences" (1980), revolucionó el campo de la gestión internacional y sigue siendo una de las referencias más citadas en la investigación sobre comportamiento organizacional.</p>

<h2>Las 6 dimensiones del modelo Hofstede</h2>

<h3>1. Distancia al Poder (PDI)</h3>
<p>Mide en qué medida los miembros menos poderosos de una sociedad aceptan y esperan que el poder se distribuya de forma desigual.</p>
<p><strong>Alta distancia al poder:</strong> La jerarquía es respetada y raramente cuestionada. Las decisiones fluyen de arriba hacia abajo. Los empleados esperan instrucciones claras.</p>
<p><strong>Baja distancia al poder:</strong> La jerarquía es instrumental, no simbólica. Se espera que todos puedan cuestionar las decisiones. La colaboración es horizontal.</p>
<p><strong>Aplicación en tu equipo:</strong> Si gestionas personas de culturas con alta distancia al poder, el silencio en una reunión no significa acuerdo; puede ser respeto a la autoridad. Crear espacios explícitos para el desacuerdo constructivo es esencial.</p>

<h3>2. Individualismo vs. Colectivismo (IDV)</h3>
<p>Mide si las personas se identifican principalmente con ellas mismas (individualismo) o con su grupo familiar, social o laboral (colectivismo).</p>
<p><strong>Culturas individualistas</strong> (EE.UU., Países Bajos, Australia): Valorar la autonomía, el logro personal y la iniciativa propia.</p>
<p><strong>Culturas colectivistas</strong> (Colombia, México, Japón, China): La lealtad al grupo es primordial. Las decisiones se toman pensando en el impacto colectivo.</p>
<p><strong>Aplicación:</strong> Los sistemas de reconocimiento individual pueden resultar incómodos para personas de culturas colectivistas. Incluye reconocimiento de equipo además del individual.</p>

<h3>3. Masculinidad vs. Feminidad (MAS)</h3>
<p>Esta dimensión, que Hofstede denominó controvertidamente, no tiene que ver con género sino con qué valores orientan la sociedad. Las culturas "masculinas" valoran la competencia, el logro, la asertividad y el éxito material. Las "femeninas" valoran la cooperación, la calidad de vida, el cuidado y las relaciones.</p>
<p><strong>Alta masculinidad</strong> (Japón, Alemania, Austria): "Vivir para trabajar". El logro profesional es central.</p>
<p><strong>Alta feminidad</strong> (Suecia, Países Bajos, Dinamarca): "Trabajar para vivir". El bienestar y el equilibrio son prioritarios.</p>
<p><strong>Aplicación:</strong> En culturas más "femeninas", ofrecer flexibilidad horaria y bienestar tiene más impacto en el engagement que los bonos económicos.</p>

<h3>4. Evitación de la Incertidumbre (UAI)</h3>
<p>Mide la tolerancia de la sociedad ante la ambigüedad y la incertidumbre. Sociedades con alta evitación de la incertidumbre prefieren reglas claras, estructura y predictibilidad.</p>
<p><strong>Alta UAI</strong> (Grecia, Portugal, España): Se prefieren procesos bien definidos, contratos detallados, planificación exhaustiva.</p>
<p><strong>Baja UAI</strong> (Singapur, Jamaica, Suecia): Más tolerancia a la ambigüedad, mayor adaptabilidad, menos necesidad de reglas.</p>
<p><strong>Aplicación:</strong> En equipos con alta UAI, la falta de claridad en los procesos genera ansiedad. Documentar procesos, dar instrucciones claras y anticipar cambios con tiempo reduce la resistencia.</p>

<h3>5. Orientación a Largo Plazo vs. Corto Plazo (LTO)</h3>
<p>Originalmente influida por el pensamiento confuciano, esta dimensión mide si la cultura valora más las virtudes orientadas al futuro (perseverancia, ahorro, adaptación) o al pasado/presente (respeto a las tradiciones, cumplimiento de obligaciones sociales).</p>
<p><strong>Orientación a largo plazo</strong> (China, Japón, Corea): Inversión en el futuro, perseverancia, adaptación pragmática.</p>
<p><strong>Orientación a corto plazo</strong> (muchos países occidentales y latinoamericanos): Resultados rápidos, respeto a las normas y tradiciones.</p>

<h3>6. Indulgencia vs. Restricción (IVR)</h3>
<p>Dimensión añadida en 2010. Mide en qué medida la sociedad permite satisfacer los deseos humanos básicos relacionados con el disfrute de la vida.</p>
<p><strong>Alta indulgencia:</strong> Énfasis en el ocio, el placer y la libertad de expresión.</p>
<p><strong>Alta restricción:</strong> Control de los deseos, normas sociales estrictas sobre el comportamiento.</p>

<h2>Hofstede en la práctica: el equipo multicultural</h2>
<p>Cuando en un equipo coexisten personas de diferentes orígenes culturales, las dimensiones de Hofstede ayudan a entender los roces que de otra forma parecerían personales.</p>
<p>Un ejemplo frecuente: el jefe español (alta evitación de incertidumbre, alta distancia al poder) que choca con el empleado neerlandés (baja distancia al poder, baja evitación de incertidumbre) que constantemente cuestiona las decisiones y prefiere improvisar. Ninguno tiene razón ni razón. Tienen culturas distintas.</p>

<h2>Limitaciones del modelo Hofstede</h2>
<p>El modelo Hofstede es una herramienta poderosa, pero tiene limitaciones importantes que debes conocer:</p>
<ul>
  <li>Describe tendencias culturales, no individuos. Dentro de cualquier cultura hay enorme variabilidad.</li>
  <li>Los datos originales tienen más de 50 años. Las culturas evolucionan, especialmente entre generaciones más jóvenes.</li>
  <li>Sesgo corporativo: la muestra original era exclusivamente de empleados de IBM.</li>
</ul>
<p>Úsalo como mapa, no como territorio. Es un punto de partida para la conversación, no una etiqueta definitiva.</p>

<h2>Cómo usa Valírica el modelo Hofstede</h2>
<p>En Valírica, hemos integrado las dimensiones de Hofstede en nuestro modelo de evaluación de equipos. Al combinar los perfiles DISC (estilo conductual individual) con las dimensiones culturales de Hofstede (marco cultural de referencia), obtenemos un mapa mucho más completo de cómo un equipo trabaja y cómo puede mejorar su cohesión y rendimiento.</p>

<div class="blog-faq">
<h2>Preguntas frecuentes sobre el modelo Hofstede</h2>
<div class="faq-item">
  <h3>¿Dónde se puede consultar el ranking de países en las dimensiones de Hofstede?</h3>
  <p>El Hofstede Insights Group mantiene una herramienta online (hofstede-insights.com) donde puedes comparar dimensiones entre países. Los datos se han actualizado en varias ocasiones desde el estudio original.</p>
</div>
<div class="faq-item">
  <h3>¿Es el modelo Hofstede aplicable a empresas pequeñas?</h3>
  <p>Sí, especialmente si el equipo incluye personas de diferentes países o regiones. Incluso dentro de un mismo país, las diferencias generacionales o de origen regional pueden reflejar variaciones en estas dimensiones.</p>
</div>
<div class="faq-item">
  <h3>¿Cómo sé la dimensión cultural de mi equipo?</h3>
  <p>En Valírica, medimos las dimensiones culturales individuales de cada miembro del equipo a través de nuestro formulario de evaluación. Esto permite identificar no solo las tendencias del equipo en su conjunto, sino también las brechas y complementariedades individuales.</p>
</div>
</div>
',
  ],

]; // fin $posts

// ─── 3. Insertar posts (ignorar duplicados) ────────────────────────────────
$stmt = $conn->prepare("
  INSERT IGNORE INTO blog_posts
    (slug, title, excerpt, content, cover_gradient, author_name, author_title, category,
     tags, status, featured, seo_title, seo_description, seo_keywords, reading_time, published_at)
  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
");

foreach ($posts as $p) {
    $stmt->bind_param(
        'ssssssssssssssis',
        $p['slug'], $p['title'], $p['excerpt'], $p['content'],
        $p['cover_gradient'], $p['author_name'], $p['author_title'], $p['category'],
        $p['tags'], $p['status'], $p['featured'],
        $p['seo_title'], $p['seo_description'], $p['seo_keywords'],
        $p['reading_time'], $p['published_at']
    );
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $success[] = "✅ Post insertado: <em>" . htmlspecialchars($p['title']) . "</em>";
        } else {
            $success[] = "⏭️ Post ya existe (omitido): <em>" . htmlspecialchars($p['title']) . "</em>";
        }
    } else {
        $errors[] = "❌ Error en post «{$p['slug']}»: " . $stmt->error;
    }
}
$stmt->close();

?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Blog Migration — Valírica</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 700px; margin: 40px auto; padding: 20px; background: #f5f5f5; color: #333; }
    h1 { color: #012133; }
    .success { background: #e8f5e9; border: 1px solid #4caf50; padding: 12px; margin: 8px 0; border-radius: 6px; }
    .error   { background: #fce4ec; border: 1px solid #f44336; padding: 12px; margin: 8px 0; border-radius: 6px; }
    .warn    { background: #fff8e1; border: 1px solid #ffc107; padding: 12px; margin: 8px 0; border-radius: 6px; }
    a.btn { display: inline-block; background: #012133; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 8px; margin-top: 20px; }
  </style>
</head>
<body>
  <h1>Blog Migration — Valírica</h1>
  <?php foreach ($success as $m): ?><div class="success"><?= $m ?></div><?php endforeach; ?>
  <?php foreach ($errors  as $m): ?><div class="error"><?= $m ?></div><?php endforeach; ?>
  <?php if (empty($errors)): ?>
    <div class="warn">⚠️ Migración completada. <strong>Elimina o protege este archivo en producción.</strong></div>
    <a class="btn" href="blog.php">→ Ver el blog</a>
  <?php endif; ?>
</body>
</html>
