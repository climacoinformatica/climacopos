@extends('web.base')

@section('titulo', 'Aviso legal y privacidad')

@section('contenido')

<section class="seccion">
    <div class="contenedor contenedor--texto">

        <h1>Aviso legal y política de privacidad</h1>

        <h2>1. Responsable del servicio</h2>

        <p>
            Este servicio es responsabilidad de <strong>CLIMACO INFORMÁTICA</strong>,
            con NIF 42190349-T, domiciliada en C/ Ángel Santana López, s/n,
            38770 Tazacorte, Santa Cruz de Tenerife.
        </p>

        <p>
            Contacto: <span class="correo" data-u="lopd" data-d="climacopos.com"></span>
        </p>

        <h2>2. Motivación</h2>

        <p>
            CLIMACO INFORMÁTICA respeta los derechos de privacidad de sus usuarios
            y reconoce la importancia de proteger los datos personales que recoge
            sobre ellos. El objetivo de este documento es informar a los usuarios
            del servicio sobre los tratamientos de datos personales que se realizan.
        </p>

        <h2>3. Datos personales tratados</h2>

        <h3>3.1. Por el mero acceso al servicio</h3>

        <p>
            Por el mero acceso al servicio, CLIMACO INFORMÁTICA recoge la dirección
            IP y otros datos relativos a la conexión y su origen. La dirección IP es
            un código que identifica la conexión a internet del usuario en un momento
            concreto. Solo el proveedor de acceso a internet del usuario puede
            identificar al abonado que tenía asignada una dirección IP en un momento
            dado.
        </p>

        <p>
            Por la propia naturaleza del servidor que da soporte al servicio, la
            dirección IP queda registrada automáticamente junto con la fecha y la
            hora del acceso. Estos datos se utilizan únicamente para gestionar el uso
            normal del servicio y realizar análisis estadísticos sobre su uso.
            CLIMACO INFORMÁTICA no facilita esta información a ningún tercero, salvo
            que esté obligada a ello por la legislación vigente, por ejemplo ante una
            solicitud oficial en el marco de una investigación policial.
        </p>

        <p>
            La base jurídica que legitima este tratamiento es la necesidad
            tecnológica para posibilitar la prestación del servicio, y los datos se
            conservan durante un plazo de un mes.
        </p>

        <h3>3.2. Formulario de contacto</h3>

        <p>
            Los datos personales que nos facilite serán incorporados a un fichero
            responsabilidad de CLIMACO INFORMÁTICA. Dichos datos serán utilizados con
            la finalidad necesaria para el cumplimiento de la relación solicitada y
            para gestionar las comunicaciones de seguimiento necesarias para resolver
            su consulta.
        </p>

        <p>
            Con esta finalidad sus datos serán tratados durante el plazo necesario
            para atender su petición.
        </p>

        <p>
            Adicionalmente, si nos autoriza a ello dando su consentimiento mediante
            la marcación de la casilla correspondiente, sus datos serán utilizados
            para enviarle comunicaciones comerciales, incluso por vía electrónica, de
            productos y servicios ofertados por CLIMACO INFORMÁTICA que puedan ser de
            su interés. Con esta finalidad sus datos serán tratados indefinidamente,
            hasta el momento en que usted decida revocar su consentimiento, oponerse
            a este tratamiento, suprimir sus datos o limitar el tratamiento.
        </p>

        <h3>3.3. Solicitud de alta de cliente</h3>

        <p>
            Los datos personales que nos facilite serán incorporados a un fichero
            responsabilidad de CLIMACO INFORMÁTICA. Dichos datos serán utilizados con
            la finalidad necesaria para el cumplimiento de la relación solicitada y
            para gestionar su relación como cliente.
        </p>

        <p>
            Con esta finalidad sus datos serán tratados mientras se mantenga la
            relación comercial entre las partes y, una vez terminada, durante el
            tiempo necesario para responder a las posibles responsabilidades legales
            que hayan podido surgir de dicha relación.
        </p>

        <p>
            Adicionalmente, como cliente de CLIMACO INFORMÁTICA y basado en el
            interés legítimo del responsable, sus datos serán utilizados para
            enviarle comunicaciones comerciales, incluso por vía electrónica, de
            productos y servicios que puedan ser de su interés. Con esta finalidad
            sus datos serán tratados indefinidamente, hasta el momento en que usted
            decida revocar su consentimiento, oponerse a este tratamiento, suprimir
            sus datos o limitar el tratamiento.
        </p>

        <h3>3.4. Qué derechos tiene el usuario y cómo ejercerlos</h3>

        <p>
            En todo momento podrá revocar, en su caso, el consentimiento prestado,
            así como ejercer sus derechos de acceso, rectificación, supresión,
            oposición, limitación del tratamiento y portabilidad, cuando dichos
            derechos sean aplicables, mediante comunicación escrita a la dirección
            arriba indicada o al correo
            <span class="correo" data-u="lopd" data-d="climacopos.com"></span>,
            aportando fotocopia de su DNI o documento equivalente y concretando su
            solicitud.
        </p>

        <p>
            Asimismo, si considera que sus datos han sido tratados de forma
            inadecuada, tendrá derecho a presentar una reclamación ante la
            <strong>Agencia Española de Protección de Datos</strong>
            (C/ Jorge Juan, 6. 28001 Madrid ·
            <a href="https://www.aepd.es" target="_blank" rel="noopener">www.aepd.es</a>).
        </p>

        <h2>4. Otros tratamientos de datos personales</h2>

        <h3>4.1. Cookies y similares</h3>

        <p>
            CLIMACO INFORMÁTICA utiliza cookies y otros mecanismos similares de
            almacenamiento y recuperación de datos en equipos terminales. Las cookies
            son ficheros que se descargan al navegador del usuario y que pueden ser
            leídos posteriormente.
        </p>

        <p>
            De esta forma, las cookies permiten diversas funcionalidades, como
            reconocer a un usuario que ya ha accedido al servicio anteriormente y
            realizar análisis sobre su uso que permitan mejorarlo. No obstante, no es
            posible averiguar la identidad del usuario a partir de las cookies que
            utiliza CLIMACO INFORMÁTICA, salvo que el usuario proporcione información
            adicional a través de otros medios y estos pudiesen vincularse con las
            cookies descargadas.
        </p>

        <p class="texto-actualizado">
            Última actualización: {{ now()->format('d/m/Y') }}
        </p>

    </div>
</section>

@endsection
