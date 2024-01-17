import { FuseNavigation } from '@fuse/types';

export const navigation: FuseNavigation[] = [
    {
        id       : 'dashboard',
        title    : 'Dashboard',
        translate: 'NAV.DASHBOARD',
        type     : 'item',
        icon     : 'dashboard',
        url      : '/dashboard'
    },
    {
        id       : 'perfil_usuario',
        title    : 'Perfil de Usuario',
        translate: 'NAV.PERFIL_USUARIO',
        type     : 'item',
        icon     : 'account_circle',
        url      : '/perfil_usuario'
    },
    {
        id       : 'sistema',
        title    : 'Sistema',
        translate: 'NAV.SISTEMA',
        icon     : 'computer',
        type     : 'collapsable',
        children : [
            {
                id       : 'variables-sistema',
                title    : 'Variables del Sistema',
                translate: 'NAV.VARIABLES_SISTEMA.TITLE',
                type     : 'item',
                icon     : 'settings',
                url      : '/sistema/variables-sistema'
            },
            {
                id       : 'festivos',
                title    : 'Festivos',
                translate: 'NAV.FESTIVOS.TITLE',
                type     : 'item',
                icon     : 'calendar_today',
                url      : '/sistema/festivos'
            },
            {
                id       : 'tiempos-aceptacion-tacita',
                title    : 'Aceptación Tácita',
                translate: 'NAV.TIEMPOS.TITLE',
                type     : 'item',
                icon     : 'access_time',
                url      : '/sistema/tiempos-aceptacion-tacita'
            },
            {
                id       : 'roles-usuarios',
                title    : 'Roles de Usuario',
                translate: 'NAV.ROLES.TITLE',
                type     : 'item',
                icon     : 'group',
                url      : '/sistema/roles'
            },
            {
                id       : 'usuarios',
                title    : 'Usuarios',
                translate: 'NAV.USUARIOS.TITLE',
                type     : 'item',
                icon     : 'account_box',
                url      : '/sistema/usuarios'
            }
        ]
    },
    {
        id       : 'parametros',
        title    : 'Parámetros DIAN',
        translate: 'NAV.PARAMETROS_DIAN',
        icon     : 'format_list_bulleted',
        type     : 'collapsable',
        children : [
            {
                id       : 'parametros-comunes',
                title    : 'Comunes',
                translate: 'NAV.COMUNES',
                icon     : 'lock',
                type     : 'collapsable',
                children : [
                    {
                        id       : 'ambiente-destino-documentos',
                        title    : 'Ambiente Destino Documentos',
                        translate: 'NAV.AMBIENTE_DESTINO_DOCUMENTOS.TITLE',
                        type     : 'item',
                        icon     : 'collections',
                        url      : '/parametros/ambiente-destino-documentos'
                    },
                    {
                        id       : 'formas-pago',
                        title    : 'Formas de Pago',
                        translate: 'NAV.FORMAS.TITLE',
                        type     : 'item',
                        icon     : 'compare',
                        url      : '/parametros/formas-pago'
                    },
                    {
                        id       : 'medios-pago',
                        title    : 'Medios de Pago',
                        translate: 'NAV.MEDIOS.TITLE',
                        type     : 'item',
                        icon     : 'credit_card',
                        url      : '/parametros/medios-pago'
                    },
                    {
                        id       : 'monedas',
                        title    : 'Monedas',
                        translate: 'NAV.MONEDAS.TITLE',
                        type     : 'item',
                        icon     : 'monetization_on',
                        url      : '/parametros/monedas'
                    },
                    {
                        id       : 'paises',
                        title    : 'Países',
                        translate: 'NAV.PAISES.TITLE',
                        type     : 'item',
                        icon     : 'flag',
                        url      : '/parametros/paises'
                    },
                    {
                        id       : 'departamentos',
                        title    : 'Departamentos',
                        translate: 'NAV.DEPARTAMENTOS.TITLE',
                        type     : 'item',
                        icon     : 'burst_mode',
                        url      : '/parametros/departamentos'
                    },
                    {
                        id       : 'municipios',
                        title    : 'Municipios',
                        translate: 'NAV.MUNICIPIOS.TITLE',
                        type     : 'item',
                        icon     : 'business',
                        url      : '/parametros/municipios'
                    },
                    {
                        id       : 'tipos-documentos',
                        title    : 'Tipos de Documentos',
                        translate: 'NAV.TIPOS_DOCUMENTOS.TITLE',
                        type     : 'item',
                        icon     : 'contacts',
                        url      : '/parametros/tipos-documentos'
                    },
                    {
                        id       : 'tipos-documentos-electronicos',
                        title    : 'Tipos Documentos Electrónicos',
                        translate: 'NAV.TIPOS_DOCUMENTOS_ELECTRONICOS.TITLE',
                        type     : 'item',
                        icon     : 'art_track',
                        url      : '/parametros/tipos-documentos-electronicos'
                    }                    
                ]
            },
            {
                id       : 'parametros-emision-recepcion',
                title    : 'Emisión / Recepción',
                translate: 'NAV.EMISION_RECEPCION',
                icon     : 'compare_arrows',
                type     : 'collapsable',
                children : [
                    {
                        id       : 'clasificacion-productos',
                        title    : 'Clasificación de Productos',
                        translate: 'NAV.CLASIFICACION_PRODUCTOS.TITLE',
                        type     : 'item',
                        icon     : 'class',
                        url      : '/parametros/clasificacion-productos'
                    },
                    {
                        id       : 'codigos-descuentos',
                        title    : 'Códigos de Descuento',
                        translate: 'NAV.CODIGOS_DESCUENTO.TITLE',
                        type     : 'item',
                        icon     : 'confirmation_number',
                        url      : '/parametros/codigos-descuentos'
                    },
                    {
                        id       : 'codigos-postales',
                        title    : 'Códigos Postales',
                        translate: 'NAV.CODIGOS_POSTALES.TITLE',
                        type     : 'item',
                        icon     : 'markunread_mailbox',
                        url      : '/parametros/codigos-postales'
                    },
                    {
                        id       : 'colombia-compra-eficiente',
                        title    : 'Colombia Compra Eficiente',
                        translate: 'NAV.COLOMBIA.TITLE',
                        type     : 'item',
                        icon     : 'account_balance_wallet',
                        url      : '/parametros/colombia-compra-eficiente'
                    },
                    {
                        id       : 'conceptos-correccion',
                        title    : 'Conceptos Corrección',
                        translate: 'NAV.CONCEPTOS_CORRECCION.TITLE',
                        type     : 'item',
                        icon     : 'cached',
                        url      : '/parametros/conceptos-correccion',
                    },
                    {
                        id       : 'conceptos-rechazo',
                        title    : 'Conceptos de Rechazo',
                        translate: 'NAV.CONCEPTOS_RECHAZO.TITLE',
                        type     : 'item',
                        icon     : 'not_interested',
                        url      : '/parametros/conceptos-rechazo',
                    },
                    {
                        id       : 'condiciones-entrega',
                        title    : 'Condiciones de Entrega',
                        translate: 'NAV.CONDICIONES.TITLE',
                        type     : 'item',
                        icon     : 'airport_shuttle',
                        url      : '/parametros/condiciones-entrega'
                    },
                    {
                        id       : 'partidas-arancelarias',
                        title    : 'Partidas Arancelarias',
                        translate: 'NAV.PARTIDAS.TITLE',
                        type     : 'item',
                        icon     : 'subtitles',
                        url      : '/parametros/partidas-arancelarias'
                    },
                    {
                        id       : 'precios-referencia',
                        title    : 'Precios de Referencia',
                        translate: 'NAV.PRECIOS.TITLE',
                        type     : 'item',
                        icon     : 'attach_money',
                        url      : '/parametros/precios-referencia'
                    },
                    {
                        id       : 'regimen-fiscal',
                        title    : 'Regimen Fiscal',
                        translate: 'NAV.REGIMEN.TITLE',
                        type     : 'item',
                        icon     : 'next_week',
                        url      : '/parametros/regimen-fiscal'
                    },
                    {
                        id       : 'responsabilidades-fiscales',
                        title    : 'Responsabilidades Fiscales',
                        translate: 'NAV.RESPONSABILIDADES_FISCALES.TITLE',
                        type     : 'item',
                        icon     : 'horizontal_split',
                        url      : '/parametros/responsabilidades-fiscales'
                    },
                    {
                        id       : 'tarifas-impuesto',
                        title    : 'Tarifas Impuesto',
                        translate: 'NAV.TARIFAS.TITLE',
                        type     : 'item',
                        icon     : 'local_atm',
                        url      : '/parametros/tarifas-impuesto',
                    },
                    {
                        id       : 'tipos-operacion',
                        title    : 'Tipos de Operación',
                        translate: 'NAV.TIPOS_OPERACION.TITLE',
                        type     : 'item',
                        icon     : 'data_usage',
                        url      : '/parametros/tipos-operacion',
                    },
                    {
                        id       : 'tipos-organizacion-juridica',
                        title    : 'Tipos Organización Jurídica',
                        translate: 'NAV.TIPOS_ORGANIZACION.TITLE',
                        type     : 'item',
                        icon     : 'folder_shared',
                        url      : '/parametros/tipos-organizacion-juridica'
                    },
                    {
                        id       : 'procedencia-vendedor',
                        title    : 'Procedencia Vendedor',
                        translate: 'NAV.PROCEDENCIA_VENDEDOR.TITLE',
                        type     : 'item',
                        icon     : 'travel_explore',
                        url      : '/parametros/procedencia-vendedor'
                    },
                    {
                        id       : 'tributos',
                        title    : 'Tributos',
                        translate: 'NAV.TRIBUTOS.TITLE',
                        type     : 'item',
                        icon     : 'account_balance',
                        url      : '/parametros/tributos',
                    },
                    {
                        id       : 'unidades',
                        title    : 'Unidades',
                        translate: 'NAV.UNIDADES.TITLE',
                        type     : 'item',
                        icon     : 'straighten',
                        url      : '/parametros/unidades',
                    },
                    {
                        id       : 'referencia-otros-documentos',
                        title    : 'Referencia Otros Docs No Tributados',
                        translate: 'NAV.REFERENCIA_OTROS_DOCUMENTOS.TITLE',
                        type     : 'item',
                        icon     : 'folder_special',
                        url      : '/parametros/referencia-otros-documentos',
                    },
                    {
                        id       : 'mandatos',
                        title    : 'Mandatos',
                        translate: 'NAV.MANDATOS.TITLE',
                        type     : 'item',
                        icon     : 'table',
                        url      : '/parametros/mandatos'
                    },
                    {
                        id       : 'formas-generacion-transmision',
                        title    : 'Formas de Generación y Transmisión',
                        translate: 'NAV.FORMAS_GENERACION_TRANSMISION.TITLE',
                        type     : 'item',
                        icon     : 'podcasts',
                        url      : '/parametros/formas-generacion-transmision'
                    }
                ]
            },
            {
                id       : 'parametros-nomina-electronica',
                title    : 'Nómina Electrónica',
                translate: 'NAV.NOMINA_ELECTRONICA',
                icon     : 'monetization_on',
                type     : 'collapsable',
                children : [
                    {
                        id       : 'periodo-nomina',
                        title    : 'Periodo Nomina',
                        translate: 'NAV.NOMINA_PERIODO.TITLE',
                        type     : 'item',
                        icon     : 'event',
                        url      : '/parametros/nomina-electronica/nomina-periodos'
                    },
                    {
                        id       : 'subtipo-trabajador',
                        title    : 'Subtipo Trabajador',
                        translate: 'NAV.SUBTIPO_TRABAJADOR.TITLE',
                        type     : 'item',
                        icon     : 'assignment_ind',
                        url      : '/parametros/nomina-electronica/subtipo-trabajador',
                        classes  : 'lp'
                    },
                    {
                        id       : 'tipo-contrato',
                        title    : 'Tipo Contrato',
                        translate: 'NAV.TIPO_CONTRATO.TITLE',
                        type     : 'item',
                        icon     : 'assignment',
                        url      : '/parametros/nomina-electronica/tipo-contrato'
                    },
                    {
                        id       : 'tipo-hora-extra-recargo',
                        title    : 'Tipo Hora Extra o Recargo',
                        translate: 'NAV.TIPO_HORA_EXTRA_RECARGO.TITLE',
                        type     : 'item',
                        icon     : 'watch_later',
                        url      : '/parametros/nomina-electronica/tipo-hora-extra-recargo'
                    },
                    {
                        id       : 'tipo-incapacidad',
                        title    : 'Tipo Incapacidad',
                        translate: 'NAV.TIPO_INCAPACIDAD.TITLE',
                        type     : 'item',
                        icon     : 'local_hospital',
                        url      : '/parametros/nomina-electronica/tipo-incapacidad'
                    },
                    {
                        id       : 'tipo-nota',
                        title    : 'Tipo Nota',
                        translate: 'NAV.TIPO_NOTA.TITLE',
                        type     : 'item',
                        icon     : 'description',
                        url      : '/parametros/nomina-electronica/tipo-nota'
                    },
                    {
                        id       : 'tipo-trabajador',
                        title    : 'Tipo Trabajador',
                        translate: 'NAV.TIPO_TRABAJADOR.TITLE',
                        type     : 'item',
                        icon     : 'accessibility',
                        url      : '/parametros/nomina-electronica/tipo-trabajador'
                    }
                ]
            },
            {
                id       : 'sector-salud',
                title    : 'Sector Salud',
                translate: 'NAV.SECTOR_SALUD',
                icon     : 'healing',
                type     : 'collapsable',
                children : [
                    {
                        id       : 'cobertura',
                        title    : 'Cobertura',
                        translate: 'NAV.COBERTURA.TITLE',
                        type     : 'item',
                        icon     : 'inbox',
                        url      : '/parametros/sector-salud/cobertura'
                    },
                    {
                        id       : 'documentos-identificacion',
                        title    : 'Documentos de Identificación',
                        translate: 'NAV.SALUD_DOCUMENTOS_IDENTIFICACION.TITLE',
                        type     : 'item',
                        icon     : 'account_circle',
                        url      : '/parametros/sector-salud/documentos-identificacion'
                    },
                    {
                        id       : 'modalidad-contratacion-pago',
                        title    : 'Modalidades Contratación y Pago',
                        translate: 'NAV.SALUD_MODALIDADES_CONTRATACION.TITLE',
                        type     : 'item',
                        icon     : 'person_pin',
                        url      : '/parametros/sector-salud/modalidad-contratacion-pago'
                    },
                    {
                        id       : 'tipo-usuario',
                        title    : 'Tipo de Usuario',
                        translate: 'NAV.TIPO_USUARIO.TITLE',
                        type     : 'item',
                        icon     : 'emoji_people',
                        url      : '/parametros/sector-salud/tipo-usuario'
                    },
                    {
                        id       : 'tipo-documentos-referenciados',
                        title    : 'Tipo Documento Referenciado',
                        translate: 'NAV.SALUD_DOCUMENTOS_REFERENCIADOS.TITLE',
                        type     : 'item',
                        icon     : 'archive',
                        url      : '/parametros/sector-salud/tipo-documentos-referenciados'
                    },
                ]
            },
            {
                id       : 'sector-transporte',
                title    : 'Sector Transporte',
                translate: 'NAV.SECTOR_TRANSPORTE',
                icon     : 'directions_bus',
                type     : 'collapsable',
                children : [
                    {
                        id       : 'transporte-registros',
                        title    : 'Registro',
                        translate: 'NAV.REGISTROS.TITLE',
                        type     : 'item',
                        icon     : 'subway',
                        url      : '/parametros/sector-transporte/registro'
                    },
                    {
                        id       : 'transporte-remesas',
                        title    : 'Remesa',
                        translate: 'NAV.REMESAS.TITLE',
                        type     : 'item',
                        icon     : 'map',
                        url      : '/parametros/sector-transporte/remesa'
                    },
                ]
            },
            {
                id       : 'sector-cambiario',
                title    : 'Sector Control Cambiario',
                translate: 'NAV.SECTOR_CONTROL_CAMBIARIO',
                icon     : 'business',
                type     : 'collapsable',
                children : [
                    {
                        id       : 'mandatos-profesional-cambios',
                        title    : 'Mandatos Profesional de Cambios',
                        translate: 'NAV.MANDATOS_PROFESIONAL_CAMBIOS.TITLE',
                        type     : 'item',
                        icon     : 'supervised_user_circle',
                        url      : '/parametros/sector-cambiario/mandatos-profesional-cambios'
                    },
                    {
                        id       : 'debida-diligencia',
                        title    : 'Debida Diligencia',
                        translate: 'NAV.DEBIDA_DILIGENCIA.TITLE',
                        type     : 'item',
                        icon     : 'supervised_user_circle',
                        url      : '/parametros/sector-cambiario/debida-diligencia'
                    }
                ]
            },
            {
                id       : 'parametros-radian',
                title    : 'Radian',
                translate: 'NAV.RADIAN',
                icon     : 'developer_board',
                type     : 'collapsable',
                children : [
                    {
                        id       : 'naturaleza-mandato',
                        title    : 'Naturaleza Mandatos',
                        translate: 'NAV.NATURALEZA_MANDATO.TITLE',
                        type     : 'item',
                        icon     : 'gavel',
                        url      : '/parametros/radian/naturaleza-mandato'
                    },
                    {
                        id       : 'tipo-mandante',
                        title    : 'Tipo Mandante',
                        translate: 'NAV.TIPO_MANDANTE.TITLE',
                        type     : 'item',
                        icon     : 'perm_contact_calendar',
                        url      : '/parametros/radian/tipo-mandante'
                    },
                    {
                        id       : 'tiempo-mandato',
                        title    : 'Tiempo Mandatos',
                        translate: 'NAV.TIEMPO_MANDATO.TITLE',
                        type     : 'item',
                        icon     : 'timer',
                        url      : '/parametros/radian/tiempo-mandato'
                    },
                    {
                        id       : 'tipo-mandatario',
                        title    : 'Tipo Mandatario',
                        translate: 'NAV.TIPO_MANDATARIO.TITLE',
                        type     : 'item',
                        icon     : 'supervisor_account',
                        url      : '/parametros/radian/tipo-mandatario'
                    },
                    {
                        id       : 'referencia-documentos-electronicos',
                        title    : 'Referencia DE',
                        translate: 'NAV.REFERENCIA_DOCUMENTO_ELECTRONICOS.TITLE',
                        type     : 'item',
                        icon     : 'art_track',
                        url      : '/parametros/radian/referencia-documentos-electronicos'
                    },
                    {
                        id       : 'tipos-pagos',
                        title    : 'Tipos Pagos',
                        translate: 'NAV.TIPOS_PAGOS.TITLE',
                        type     : 'item',
                        icon     : 'credit_card',
                        url      : '/parametros/radian/tipos-pagos'
                    },
                    {
                        id       : 'evento-documento-electronico',
                        title    : 'Evento Documento Electronico',
                        translate: 'NAV.EVENTO_DOCUMENTO_ELECTRONICO.TITLE',
                        type     : 'item',
                        icon     : 'assignment',
                        url      : '/parametros/radian/evento-documento-electronico'
                    },
                    {
                        id       : 'tipo-operacion',
                        title    : 'Tipo Operacion',
                        translate: 'NAV.TIPO_OPERACION.TITLE',
                        type     : 'item',
                        icon     : 'clear_all',
                        url      : '/parametros/radian/tipo-operacion'
                    },
                    {
                        id       : 'factor',
                        title    : 'Factor',
                        translate: 'NAV.FACTOR.TITLE',
                        type     : 'item',
                        icon     : 'details',
                        url      : '/parametros/radian/factor'
                    },
                    {
                        id       : 'endoso',
                        title    : 'Endosos',
                        translate: 'NAV.ENDOSO.TITLE',
                        type     : 'item',
                        icon     : 'device_hub',
                        url      : '/parametros/radian/endoso'
                    },
                    {
                        id       : 'alcance-mandato',
                        title    : 'Alcance Mandatos',
                        translate: 'NAV.ALCANCE_MANDATO.TITLE',
                        type     : 'item',
                        icon     : 'donut_small',
                        url      : '/parametros/radian/alcance-mandato'
                    },
                    {
                        id       : 'roles',
                        title    : 'Roles',
                        translate: 'NAV.ROLES_RADIAN.TITLE',
                        type     : 'item',
                        icon     : 'pan_tool',
                        url      : '/parametros/radian/roles'
                    }
                ]
            }
        ]
    },
    {
        id       : 'configuracion',
        title    : 'Configuración',
        translate: 'NAV.CONFIGURACION',
        icon     : 'settings',
        type     : 'collapsable',
        children : [
            {
                id       : 'configuracion-comunes',
                title    : 'Comunes',
                translate: 'NAV.COMUNES',
                icon     : 'lock',
                type     : 'collapsable',
                children : [
                    {
                        id       : 'oferentes',
                        title    : 'OFEs',
                        translate: 'NAV.OFES.TITLE',
                        type     : 'item',
                        icon     : 'business_center',
                        url      : '/configuracion/oferentes'
                    },
                    {
                        id       : 'software-proveedor-tecnologico',
                        title    : 'Software Proveedor Tecnológico',
                        translate: 'NAV.SPTS.TITLE',
                        type     : 'item',
                        icon     : 'computer',
                        url      : '/configuracion/software-proveedor-tecnologico'
                    },
                    {
                        id       : 'configuracion-grupos-trabajo',
                        title    : '{GRUPOS_TRABAJO}',
                        translate: 'NAV.GRUPOS_TRABAJO', 
                        icon     : 'account_circle',
                        type     : 'collapsable',
                        children : [
                            {
                                id       : 'administracion-grupos-trabajo',
                                title    : 'Administración',
                                translate: 'NAV.ADM_GRUPOS_TRABAJO.TITLE',
                                type     : 'item',
                                icon     : 'contacts',
                                url      : '/configuracion/grupos-trabajo/administracion'
                            },
                            {
                                id       : 'asociar-usuarios',
                                title    : 'Asociar Usuarios',
                                translate: 'NAV.ASOCIAR_USUARIOS.TITLE',
                                type     : 'item',
                                icon     : 'account_box',
                                url      : '/configuracion/grupos-trabajo/asociar-usuarios'
                            },
                            {
                                id       : 'asociar-proveedores',
                                title    : 'Asociar Proveedores',
                                translate: 'NAV.ASOCIAR_PROVEEDORES.TITLE',
                                type     : 'item',
                                icon     : 'supervised_user_circle',
                                url      : '/configuracion/grupos-trabajo/asociar-proveedores'
                            }
                        ]
                    },
                    {
                        id       : 'xpath-documentos-electronicos-estandar',
                        title    : 'XPath DE Estándar',
                        translate: 'NAV.XPATH_DOCUMENTOS_ELECTRONICOS_ESTANDAR.TITLE',
                        type     : 'item',
                        icon     : 'personal_video',
                        url      : '/configuracion/xpath-documentos-electronicos/estandar'
                    },
                    {
                        id       : 'xpath-documentos-electronicos-personalizados',
                        title    : 'XPath DE Personalizados',
                        translate: 'NAV.XPATH_DOCUMENTOS_ELECTRONICOS_PERSONALIZADOS.TITLE',
                        type     : 'item',
                        icon     : 'display_settings',
                        url      : '/configuracion/xpath-documentos-electronicos/personalizados'
                    }
                ]
            },
            {
                id       : 'configuracion-emision',
                title    : 'Emisión',
                translate: 'NAV.CONFIGURACION_EMISION',
                icon     : 'arrow_forward',
                type     : 'collapsable',
                children : [
                    {
                        id       : 'adquirentes',
                        title    : 'Adquirentes',
                        translate: 'NAV.ADQUIRENTES.TITLE',
                        type     : 'item',
                        icon     : 'account_circle',
                        url      : '/configuracion/adquirentes'
                    },
                    {
                        id       : 'autorizados',
                        title    : 'Autorizados (Representación)',
                        translate: 'NAV.AUTORIZADOS.TITLE',
                        type     : 'item',
                        icon     : 'assignment_ind',
                        url      : '/configuracion/autorizados'
                    },
                    {
                        id       : 'responsables',
                        title    : 'Responsables Entrega Bienes',
                        translate: 'NAV.RESPONSABLES.TITLE',
                        type     : 'item',
                        icon     : 'contact_phone',
                        url      : '/configuracion/responsables'
                    },
                    {
                        id       : 'vendedores',
                        title    : 'Vendedores DS',
                        translate: 'NAV.VENDEDORES.TITLE',
                        type     : 'item',
                        icon     : 'folder_shared',
                        url      : '/configuracion/vendedores'
                    },
                    {
                        id       : 'resoluciones-facturacion',
                        title    : 'Resoluciones de Facturación',
                        translate: 'NAV.RFAS.TITLE',
                        type     : 'item',
                        icon     : 'receipt',
                        url      : '/configuracion/resoluciones-facturacion'
                    }
                ]
            },
            {
                id       : 'configuracion-recepcion',
                title    : 'Recepción',
                translate: 'NAV.CONFIGURACION_RECEPCION',
                icon     : 'arrow_back',
                type     : 'collapsable',
                children : [
                    {
                        id       : 'proveedores',
                        title    : 'Proveedores',
                        translate: 'NAV.PROVEEDORES.TITLE',
                        type     : 'item',
                        icon     : 'supervised_user_circle',
                        url      : '/configuracion/proveedores'
                    },
                    {
                        id       : 'autorizaciones-eventos-dian',
                        title    : 'Autorizaciones Eventos DIAN',
                        translate: 'NAV.AUTORIZACIONES_EVENTOS_DIAN.TITLE',
                        type     : 'item',
                        icon     : 'contacts',
                        url      : '/configuracion/autorizaciones-eventos-dian'
                    },
                    {
                        id       : 'administracion-recepcion-erp',
                        title    : 'Administración Reglas Recepción',
                        translate: 'NAV.ADMINISTRACION_RECEPCION_ERP.TITLE',
                        type     : 'item',
                        icon     : 'dashboard',
                        url      : '/configuracion/administracion-recepcion-erp'
                    },
                    {
                        id       : 'centros-costo',
                        title    : 'Centros de Costo',
                        translate: 'NAV.CENTROS_COSTO.TITLE',
                        type     : 'item',
                        icon     : 'my_location',
                        url      : '/configuracion/recepcion/centros-costo'
                    },
                    {
                        id       : 'causales-devolucion',
                        title    : 'Causales Devolución',
                        translate: 'NAV.CAUSALES_DEVOLUCION.TITLE',
                        type     : 'item',
                        icon     : 'autorenew',
                        url      : '/configuracion/recepcion/causales-devolucion'
                    },
                    {
                        id       : 'centros-operacion',
                        title    : 'Centros Operación',
                        translate: 'NAV.CENTROS_OPERACION.TITLE',
                        type     : 'item',
                        icon     : 'apps',
                        url      : '/configuracion/recepcion/centros-operacion'
                    },
                    {
                        id       : 'fondos',
                        title    : 'Fondos',
                        translate: 'NAV.FONDOS.TITLE',
                        type     : 'item',
                        icon     : 'account_balance',
                        url      : '/configuracion/recepcion/fondos'
                    }
                ]
            },
            {
                id       : 'configuracion-radian',
                title    : 'Radian',
                translate: 'NAV.CONFIGURACION_RADIAN',
                icon     : 'autorenew',
                type     : 'collapsable',
                children : [
                    {
                        id       : 'actores-radian',
                        title    : 'Actores',
                        translate: 'NAV.ACTORES_RADIAN.TITLE',
                        type     : 'item',
                        icon     : 'supervisor_account',
                        url      : '/configuracion/radian-actores'
                    }
                ]
            },
            {
                id       : 'nomina-electonica',
                title    : 'Nómina Electrónica',
                translate: 'NAV.CONFIGURACION_NOMINA_ELECTRONICA',
                icon     : 'monetization_on',
                type     : 'collapsable',
                children : [
                    {
                        id       : 'empleadores',
                        title    : 'Empleador',
                        translate: 'NAV.EMPLEADORES.TITLE',
                        type     : 'item',
                        icon     : 'group_work',
                        url      : '/configuracion/nomina-electronica/empleadores'
                    },
                    {
                        id       : 'trabajadores',
                        title    : 'Trabajador',
                        translate: 'NAV.TRABAJADORES.TITLE',
                        type     : 'item',
                        icon     : 'group',
                        url      : '/configuracion/nomina-electronica/trabajadores'
                    }
                ]
            },
            {
                id       : 'configuracion-ecm',
                title    : 'Integración openECM',
                translate: 'NAV.INTEGRACION_ECM',
                icon     : 'open_in_browser',
                type     : 'collapsable',
                children : [
                    {
                        id       : 'usuarios-ecm',
                        title    : 'Usuarios openECM',
                        translate: 'NAV.USUARIOS_ECM.TITLE',
                        type     : 'item',
                        icon     : 'group',
                        url      : '/configuracion/usuarios-ecm'
                    }
                ]
            },
            {
                id       : 'configuracion-reportes',
                title    : 'Reportes',
                translate: 'NAV.CONFIGURACION_REPORTES',
                icon     : 'file_copy',
                type     : 'collapsable',
                children : [
                    {
                        id       : 'configuracion-reportes-background',
                        title    : 'Reportes Background',
                        translate: 'NAV.CONFIGURACION_REPORTES_BACKGROUND.TITLE',
                        type     : 'item',
                        icon     : 'assignment_turned_in',
                        url      : '/configuracion/reportes/background'
                    }
                ]
            }
        ]
    },
    {
        id       : 'facturacion-web',
        title    : 'Facturación Web',
        translate: 'NAV.FACTURACION_WEB.TITLE',
        icon     : 'how_to_vote',
        type     : 'collapsable',
        children : [
            {
                id       : 'facturacion-web-parametros',
                title    : 'Parámetros',
                translate: 'NAV.FACTURACION_WEB.PARAMETROS.TITLE',
                icon     : 'settings',
                type     : 'collapsable',
                children : [
                    {
                        id       : 'facturacion-web-parametros-control-consecutivos',
                        title    : 'Control Consecutivos',
                        translate: 'NAV.FACTURACION_WEB.PARAMETROS.CONTROL_CONSECUTIVOS.TITLE',
                        type     : 'item',
                        icon     : 'forward',
                        url      : '/facturacion-web/parametros/control-consecutivos'
                    },
                    {
                        id       : 'facturacion-web-parametros-cargos',
                        title    : 'Cargos',
                        translate: 'NAV.FACTURACION_WEB.PARAMETROS.CARGOS.TITLE',
                        type     : 'item',
                        icon     : 'forward',
                        url      : '/facturacion-web/parametros/cargos'
                    },
                    {
                        id       : 'facturacion-web-parametros-descuentos',
                        title    : 'Descuentos',
                        translate: 'NAV.FACTURACION_WEB.PARAMETROS.DESCUENTOS.TITLE',
                        type     : 'item',
                        icon     : 'forward',
                        url      : '/facturacion-web/parametros/descuentos'
                    },
                    {
                        id       : 'facturacion-web-parametros-productos',
                        title    : 'Productos',
                        translate: 'NAV.FACTURACION_WEB.PARAMETROS.PRODUCTOS.TITLE',
                        type     : 'item',
                        icon     : 'forward',
                        url      : '/facturacion-web/parametros/productos'
                    },
                ]
            },
            {
                id       : 'facturacion-web-crear-documento',
                title    : 'Crear Documento',
                translate: 'NAV.FACTURACION_WEB.CREAR_DOCUMENTO.TITLE',
                icon     : 'unarchives',
                type     : 'collapsable',
                children : [
                    {
                        id       : 'facturacion-web-crear-documento-factura',
                        title    : 'Factura',
                        translate: 'NAV.FACTURACION_WEB.CREAR_DOCUMENTO.FACTURA.TITLE',
                        type     : 'item',
                        icon     : 'forward',
                        url      : '/facturacion-web/crear-documento/factura'
                    },
                    {
                        id       : 'facturacion-web-crear-documento-nota-credito',
                        title    : 'Nota Crédito',
                        translate: 'NAV.FACTURACION_WEB.CREAR_DOCUMENTO.NOTA_CREDITO.TITLE',
                        type     : 'item',
                        icon     : 'forward',
                        url      : '/facturacion-web/crear-documento/nota-credito'
                    },
                    {
                        id       : 'facturacion-web-crear-documento-nota-debito',
                        title    : 'Nota Débito',
                        translate: 'NAV.FACTURACION_WEB.CREAR_DOCUMENTO.NOTA_DEBITO.TITLE',
                        type     : 'item',
                        icon     : 'forward',
                        url      : '/facturacion-web/crear-documento/nota-debito'
                    },
                    {
                        id       : 'facturacion-web-crear-documento-soporte',
                        title    : 'Documento Soporte',
                        translate: 'NAV.FACTURACION_WEB.CREAR_DOCUMENTO.SOPORTE.TITLE',
                        type     : 'item',
                        icon     : 'forward',
                        url      : '/facturacion-web/crear-documento/documento-soporte'
                    },
                    {
                        id       : 'facturacion-web-crear-documento-ds-nota-credito',
                        title    : 'Nota Crédito DS',
                        translate: 'NAV.FACTURACION_WEB.CREAR_DOCUMENTO.NOTA_CREDITO_DS.TITLE',
                        type     : 'item',
                        icon     : 'forward',
                        url      : '/facturacion-web/crear-documento/ds-nota-credito'
                    }
                ]
            }
        ]
    },
    {
        id       : 'emision',
        title    : 'Emisión',
        translate: 'NAV.EMISION',
        icon     : 'cloud_upload',
        type     : 'collapsable',
        children : [
            {
                id       : 'documentos-cco',
                title    : 'Documentos CCO',
                translate: 'NAV.DOCUMENTOS_CCO.TITLE',
                icon     : 'file_copy',
                type     : 'collapsable',
                children : [
                    {
                        id       : 'documentos-cco-parametros',
                        title    : 'Parámetros',
                        translate: 'NAV.DOCUMENTOS_CCO.PARAMETROS.TITLE',
                        icon     : 'settings',
                        type     : 'collapsable',
                        children : [
                            {
                                id       : 'documentos-cco-parametros-datos-comunes',
                                title    : 'Datos Comunes',
                                translate: 'NAV.DOCUMENTOS_CCO.PARAMETROS.DATOS_COMUNES.TITLE',
                                type     : 'item',
                                icon     : 'forward',
                                url      : '/emision/documentos-cco/parametros/datos-comunes'
                            },
                            {
                                id       : 'documentos-cco-parametros-datos-fijos',
                                title    : 'Datos Fijos',
                                translate: 'NAV.DOCUMENTOS_CCO.PARAMETROS.DATOS_FIJOS.TITLE',
                                type     : 'item',
                                icon     : 'forward',
                                url      : '/emision/documentos-cco/parametros/datos-fijos'
                            },
                            {
                                id       : 'documentos-cco-parametros-datos-variables',
                                title    : 'Datos Variables',
                                translate: 'NAV.DOCUMENTOS_CCO.PARAMETROS.DATOS_VARIABLES.TITLE',
                                type     : 'item',
                                icon     : 'forward',
                                url      : '/emision/documentos-cco/parametros/datos-variables'
                            },
                            {
                                id       : 'documentos-cco-parametros-extracargos',
                                title    : 'Extracargos',
                                translate: 'NAV.DOCUMENTOS_CCO.PARAMETROS.EXTRACARGOS.TITLE',
                                type     : 'item',
                                icon     : 'forward',
                                url      : '/emision/documentos-cco/parametros/extracargos'
                            },
                            {
                                id       : 'documentos-cco-parametros-productos',
                                title    : 'Productos',
                                translate: 'NAV.DOCUMENTOS_CCO.PARAMETROS.PRODUCTOS.TITLE',
                                type     : 'item',
                                icon     : 'forward',
                                url      : '/emision/documentos-cco/parametros/productos'
                            }
                        ]
                    },
                    {
                        id       : 'documentos-cco-nuevo-documento',
                        title    : 'Nuevo Documento',
                        translate: 'NAV.DOCUMENTOS_CCO.NUEVO_DOCUMENTO.TITLE',
                        icon     : 'note_add',
                        type     : 'collapsable',
                        children : [
                            {
                                id       : 'documentos-cco-nuevo-documento-factura',
                                title    : 'Factura',
                                translate: 'NAV.DOCUMENTOS_CCO.NUEVO_DOCUMENTO.FACTURA.TITLE',
                                type     : 'item',
                                icon     : 'forward',
                                url      : '/emision/documentos-cco/nuevo-documento/factura'
                            },
                        ]
                    }
                ]
            },
            {
                id       : 'creacion-documentos-por-excel',
                title    : 'Creación Documentos por Excel',
                translate: 'NAV.CREACION_DOCUMENTOS_POR_EXCEL.TITLE',
                type     : 'item',
                icon     : 'border_all',
                url      : '/emision/creacion-documentos-por-excel'
            },
            {
                id       : 'documentos-sin-envio',
                title    : 'Documentos Sin Envío',
                translate: 'NAV.DOCUMENTOS_SIN_ENVIO.TITLE',
                type     : 'item',
                icon     : 'work_off',
                url      : '/emision/documentos-sin-envio'
            },
            {
                id       : 'documentos-enviados',
                title    : 'Documentos Enviados',
                translate: 'NAV.DOCUMENTOS_ENVIADOS.TITLE',
                type     : 'item',
                icon     : 'present_to_all',
                url      : '/emision/documentos-enviados'
            },
            {
                id       : 'emision-documentos-anexos',
                title    : 'Documentos Anexos',
                translate: 'NAV.DOCUMENTOS_ANEXOS.TITLE',
                type     : 'item',
                icon     : 'attach_file',
                url      : '/emision/documentos-anexos'
            },
            {
                id       : 'emision-reportes',
                title    : 'Reportes',
                translate: 'NAV.REPORTES.TITLE',
                icon     : 'file_copy',
                type     : 'collapsable',
                children : [
                    {
                        id       : 'emision-reportes-dhl-express',
                        title    : 'DHL Express',
                        translate: 'NAV.DHL_EXPRESS.TITLE',
                        type     : 'item',
                        icon     : 'forward',
                        url      : '/emision/reportes/dhl-express'
                    },
                    {
                        id       : 'emision-documentos-procesados',
                        title    : 'Documentos Procesados',
                        translate: 'NAV.DOCUMENTOS_PROCESADOS.TITLE',
                        type     : 'item',
                        icon     : 'assessment',
                        url      : '/emision/reportes/documentos-procesados'
                    },
                    {
                        id       : 'emision-notificacion-documentos',
                        title    : 'Notificación Documentos',
                        translate: 'NAV.NOTIFICACION_DOCUMENTOS.TITLE',
                        type     : 'item',
                        icon     : 'alarm_on',
                        url      : '/emision/reportes/notificacion-documentos'
                    },
                    {
                        id       : 'emision-reportes-background',
                        title    : 'Reportes Background',
                        translate: 'NAV.REPORTES.REPORTES_BACKGROUND.TITLE',
                        type     : 'item',
                        icon     : 'assignment_turned_in',
                        url      : '/emision/reportes/background'
                    }
                ]
            },
        ]
    },
    {
        id       : 'documento-soporte',
        title    : 'Documento Soporte',
        translate: 'NAV.DOCUMENTO_SOPORTE',
        icon     : 'file_copy',
        type     : 'collapsable',
        children : [
            {
                id       : 'creacion-documentos-por-excel-ds',
                title    : 'Creación Documentos por Excel',
                translate: 'NAV.DOCUMENTO_SOPORTE.CREACION_DOCUMENTOS_POR_EXCEL.TITLE',
                type     : 'item',
                icon     : 'border_all',
                url      : '/documento-soporte/creacion-documentos-por-excel'
            },
            {
                id       : 'documentos-sin-envio-ds',
                title    : 'Documentos Sin Envío',
                translate: 'NAV.DOCUMENTO_SOPORTE.DOCUMENTOS_SIN_ENVIO.TITLE',
                type     : 'item',
                icon     : 'work_off',
                url      : '/documento-soporte/documentos-sin-envio'
            },
            {
                id       : 'documentos-enviados-ds',
                title    : 'Documentos Enviados',
                translate: 'NAV.DOCUMENTO_SOPORTE.DOCUMENTOS_ENVIADOS.TITLE',
                type     : 'item',
                icon     : 'present_to_all',
                url      : '/documento-soporte/documentos-enviados'
            },
            {
                id       : 'documento-soporte-reportes',
                title    : 'Reportes',
                translate: 'NAV.DOCUMENTO_SOPORTE.REPORTES.TITLE',
                icon     : 'file_copy',
                type     : 'collapsable',
                children : [
                    {
                        id       : 'documento-soporte-documentos-procesados',
                        title    : 'Documentos Procesados',
                        translate: 'NAV.DOCUMENTO_SOPORTE.REPORTES.DOCUMENTOS_PROCESADOS.TITLE',
                        type     : 'item',
                        icon     : 'assessment',
                        url      : '/documento-soporte/reportes/documentos-procesados'
                    },
                    {
                        id       : 'documento-soporte-notificacion-documentos',
                        title    : 'Notificación Documentos',
                        translate: 'NAV.DOCUMENTO_SOPORTE.REPORTES.NOTIFICACION_DOCUMENTOS.TITLE',
                        type     : 'item',
                        icon     : 'alarm_on',
                        url      : '/documento-soporte/reportes/notificacion-documentos'
                    },
                    {
                        id       : 'documento-soporte-reportes-background',
                        title    : 'Reportes Background',
                        translate: 'NAV.DOCUMENTO_SOPORTE.REPORTES.REPORTES_BACKGROUND.TITLE',
                        type     : 'item',
                        icon     : 'assignment_turned_in',
                        url      : '/documento-soporte/reportes/background'
                    }
                ]
            },
        ]
    },
    {
        id       : 'recepcion',
        title    : 'Recepción',
        translate: 'NAV.RECEPCION.TITLE',
        icon     : 'cloud_download',
        type     : 'collapsable',
        children : [
            {
                id       : 'documentos-manuales-recepcion',
                title    : 'Documentos Manuales',
                translate: 'NAV.RECEPCION.DOCUMENTOS_MANUALES.TITLE',
                type     : 'item',
                icon     : 'how_to_vote',
                url      : '/recepcion/documentos-manuales'
            },
            {
                id       : 'correos-recibidos',
                title    : 'Correos Recibidos',
                translate: 'NAV.RECEPCION.CORREOS_RECIBIDOS.TITLE',
                type     : 'item',
                icon     : 'mark_email_read',
                url      : '/recepcion/correos-recibidos'
            },
            {
                id       : 'documentos-recibidos',
                title    : 'Documentos Recibidos',
                translate: 'NAV.RECEPCION.DOCUMENTOS_RECIBIDOS.TITLE',
                type     : 'item',
                icon     : 'archive',
                url      : '/recepcion/documentos-recibidos'
            },
            {
                id       : 'gestion-documentos',
                title    : 'Gestión Documentos',
                translate: 'NAV.RECEPCION.GESTION_DOCUMENTOS.TITLE',
                icon     : 'drive_folder_upload',
                type     : 'collapsable',
                children : [
                    {
                        id       : 'fe-doc-soporte-electronico',
                        title    : 'Fe/Doc Soporte Electrónico',
                        translate: 'NAV.RECEPCION.GESTION_DOCUMENTOS.FE_DOC_SOPORTE_ELECTRONICO.TITLE',
                        type     : 'item',
                        icon     : 'filter_1',
                        url      : '/recepcion/gestion-documentos/fe-doc-soporte-electronico'
                    },
                    {
                        id       : 'pendiente-revision',
                        title    : 'Pendiente Revisión',
                        translate: 'NAV.RECEPCION.GESTION_DOCUMENTOS.PENDIENTE_REVISION.TITLE',
                        type     : 'item',
                        icon     : 'filter_2',
                        url      : '/recepcion/gestion-documentos/pendiente-revision'
                    },
                    {
                        id       : 'pendiente-aprobar-conformidad',
                        title    : 'Pendiente Aprobar Conformidad',
                        translate: 'NAV.RECEPCION.GESTION_DOCUMENTOS.PENDIENTE_APROBAR_CONFORMIDAD.TITLE',
                        type     : 'item',
                        icon     : 'filter_3',
                        url      : '/recepcion/gestion-documentos/pendiente-aprobar-conformidad'
                    },
                    {
                        id       : 'pendiente-reconocimiento-contable',
                        title    : 'Pendiente Reconocimiento Contable',
                        translate: 'NAV.RECEPCION.GESTION_DOCUMENTOS.PENDIENTE_RECONOCIMIENTO_CONTABLE.TITLE',
                        type     : 'item',
                        icon     : 'filter_4',
                        url      : '/recepcion/gestion-documentos/pendiente-reconocimiento-contable'
                    },
                    {
                        id       : 'pendiente-revision-impuestos',
                        title    : 'Pendiente Revisión de Impuestos',
                        translate: 'NAV.RECEPCION.GESTION_DOCUMENTOS.PENDIENTE_REVISION_IMPUESTOS.TITLE',
                        type     : 'item',
                        icon     : 'filter_5',
                        url      : '/recepcion/gestion-documentos/pendiente-revision-impuestos'
                    },
                    {
                        id       : 'pendiente-pago',
                        title    : 'Pendiente de Pago',
                        translate: 'NAV.RECEPCION.GESTION_DOCUMENTOS.PENDIENTE_PAGO.TITLE',
                        type     : 'item',
                        icon     : 'filter_6',
                        url      : '/recepcion/gestion-documentos/pendiente-pago'
                    },
                    {
                        id       : 'fe-doc-soporte-electronico-gestionado',
                        title    : 'Fe/Doc Soporte Electrónico Gestionado',
                        translate: 'NAV.RECEPCION.GESTION_DOCUMENTOS.FE_DOC_SOPORTE_ELECTRONICO_GESTIONADO.TITLE',
                        type     : 'item',
                        icon     : 'filter_7',
                        url      : '/recepcion/gestion-documentos/fe-doc-soporte-electronico-gestionado'
                    },
                ]
            },
            {
                id       :'autorizaciones',
                title    :'Autorizaciones',
                translate:'NAV.RECEPCION.AUTORIZACIONES.TITLE',
                icon     :'done_outline',
                type     :'collapsable',
                children : [
                    {
                        id       :'autorizacion-etapas',
                        title    :'Autorización Etapas',
                        translate:'NAV.RECEPCION.AUTORIZACIONES.AUTORIZACION_ETAPAS.TITLE',
                        type     :'item',
                        icon     :'assessment',
                        url      :'/recepcion/autorizaciones/autorizacion-etapas'
                    }
                ]
            },
            {
                id       : 'validacion-documentos',
                title    : 'Validación Documentos',
                translate: 'NAV.RECEPCION.VALIDACION_DOCUMENTOS.TITLE',
                type     : 'item',
                icon     : 'assignment',
                url      : '/recepcion/validacion-documentos'
            },
            {
                id       : 'documentos-anexos',
                title    : 'Documentos Anexos',
                translate: 'NAV.RECEPCION.DOCUMENTOS_ANEXOS.TITLE',
                type     : 'collapsable',
                icon     : 'drive_folder_upload',
                children : [
                    {
                        id       : 'documentos-anexos-cargar',
                        title    : 'Cargar Anexos',
                        translate: 'NAV.RECEPCION.DOCUMENTOS_ANEXOS.CARGAR_ANEXOS.TITLE',
                        type     : 'item',
                        icon     : 'note_add',
                        url      : '/recepcion/documentos-anexos/cargar-anexos'
                    },
                    {
                        id       : 'documentos-anexos-log-errores',
                        title    : 'Log Errores Anexos',
                        translate: 'NAV.RECEPCION.DOCUMENTOS_ANEXOS.LOG_ERRORES_ANEXOS.TITLE',
                        type     : 'item',
                        icon     : 'error_outline',
                        url      : '/recepcion/documentos-anexos/log-errores-anexos'
                    }
                ]
            },
            {
                id       : 'documentos-no-electronicos',
                title    : 'Documentos No Electrónicos',
                translate: 'NAV.RECEPCION.DOCUMENTOS_NO_ELECTRONICOS.TITLE',
                type     : 'item',
                icon     : 'work_off',
                url      : '/recepcion/documentos-no-electronicos'
            },
            {
                id       : 'recepcion-reportes',
                title    : 'Reportes',
                translate: 'NAV.RECEPCION.RECEPCION_REPORTES.TITLE',
                icon     : 'file_copy',
                type     : 'collapsable',
                children : [
                    {
                        id       : 'recepcion-documentos-procesados',
                        title    : 'Documentos Procesados',
                        translate: 'NAV.RECEPCION.RECEPCION_REPORTES.RECEPCION_DOCUMENTOS_PROCESADOS.TITLE',
                        type     : 'item',
                        icon     : 'assessment',
                        url      : '/recepcion/reportes/documentos-procesados'
                    },
                    {
                        id       : 'recepcion-reportes-reporte-gestion-documentos',
                        title    : 'Reporte Gestión Documentos',
                        translate: 'NAV.RECEPCION.RECEPCION_REPORTES.RECEPCION_REPORTES_REPORTE_GESTION_DOCUMENTOS.TITLE',
                        type     : 'item',
                        icon     : 'assignment',
                        url      : '/recepcion/reportes/reporte-gestion-documentos'
                    },
                    {
                        id       : 'recepcion-reportes-log-validacion-documentos',
                        title    : 'Log Validación Documentos',
                        translate: 'NAV.RECEPCION.RECEPCION_REPORTES.RECEPCION_REPORTES_LOG_VALIDACION_DOCUMENTOS.TITLE',
                        type     : 'item',
                        icon     : 'format_list_bulleted',
                        url      : '/recepcion/reportes/log-validacion-documentos'
                    },
                    {
                        id       : 'recepcion-reportes-reporte-dependencias',
                        title    : 'Reporte Dependencias',
                        translate: 'NAV.RECEPCION.RECEPCION_REPORTES.RECEPCION_REPORTES_REPORTE_DEPENDENCIAS.TITLE',
                        type     : 'item',
                        icon     : 'folder_shared',
                        url      : '/recepcion/reportes/reporte-dependencias'
                    },
                    {
                        id       : 'recepcion-reportes-background',
                        title    : 'Reportes Background',
                        translate: 'NAV.RECEPCION.RECEPCION_REPORTES.RECEPCION_REPORTES_BACKGROUND.TITLE',
                        type     : 'item',
                        icon     : 'assignment_turned_in',
                        url      : '/recepcion/reportes/background'
                    },
                ]
            }
        ]
    },
    {
        id       : 'nomina-electronica',
        title    : 'Nómina Electrónica',
        translate: 'NAV.NOMINA_ELECTRONICA.TITLE',
        icon     : 'cloud_download',
        type     : 'collapsable',
        children : [
            {
                id       : 'creacion-documentos-por-excel',
                title    : 'Creación Documentos por Excel',
                translate: 'NAV.NOMINA_ELECTRONICA.CREACION_DOCUMENTOS_POR_EXCEL.TITLE',
                type     : 'item',
                icon     : 'border_all',
                url      : '/nomina-electronica/creacion-documentos-por-excel'
            },
            {
                id       : 'documentos-sin-envio-dn',
                title    : 'Documentos Sin Envío',
                translate: 'NAV.NOMINA_ELECTRONICA.DOCUMENTOS_SIN_ENVIO_DN.TITLE',
                type     : 'item',
                icon     : 'how_to_vote',
                url      : '/nomina-electronica/documentos-sin-envio'
            },
            {
                id       : 'documentos-enviados-dn',
                title    : 'Documentos Enviados',
                translate: 'NAV.NOMINA_ELECTRONICA.DOCUMENTOS_ENVIADOS_DN.TITLE',
                type     : 'item',
                icon     : 'archive',
                url      : '/nomina-electronica/documentos-enviados'
            },
            {
                id       : 'reportes-dn',
                title    : 'Reportes',
                translate: 'NAV.NOMINA_ELECTRONICA.NOMINA_REPORTES.TITLE',
                icon     : 'file_copy',
                type     : 'collapsable',
                children : [
                    {
                        id       : 'reportes-dn-background',
                        title    : 'Reportes Background',
                        translate: 'NAV.NOMINA_ELECTRONICA.NOMINA_REPORTES.NOMINA_REPORTES_BACKGROUND.TITLE',
                        type     : 'item',
                        icon     : 'assignment_turned_in',
                        url      : '/nomina-electronica/reportes/background'
                    }
                ]
            }
        ]
    },
    {
        id       : 'radian-registro',
        title    : 'Radian',
        translate: 'NAV.RADIAN_REGISTRO_DOCUMENTOS.TITLE',
        icon     : 'all_inclusive',
        type     : 'collapsable',
        children : [
            {
                id       : 'radian-registro-documentos',
                title    : 'Registro Documentos',
                translate: 'NAV.RADIAN_REGISTRO_DOCUMENTOS.REGISTRO_DOCUMENTOS.TITLE',
                icon     : 'art_track',
                type     : 'collapsable',
                children : [
                    {
                        id       : 'radian-registro-documentos-registrar',
                        title    : 'Registrar',
                        translate: 'NAV.RADIAN_REGISTRO_DOCUMENTOS.REGISTRO_DOCUMENTOS.REGISTRO.TITLE',
                        type     : 'item',
                        icon     : 'assessment',
                        url      : '/radian/registro-documentos/registrar'
                    },
                    {
                        id       : 'radian-log-errores',
                        title    : 'Log de Errores',
                        translate: 'NAV.RADIAN_REGISTRO_DOCUMENTOS.REGISTRO_DOCUMENTOS.LOG_ERRORES.TITLE',
                        type     : 'item',
                        icon     : 'attachment',
                        url      : '/radian/registro-documentos/log-errores'
                    }
                ]
            },
            {
                id       : 'radian-documentos',
                title    : 'Documentos Radian',
                translate: 'NAV.RADIAN_REGISTRO_DOCUMENTOS.REGISTRO_DOCUMENTOS.DOCUMENTOS.TITLE',
                type     : 'item',
                icon     : 'chrome_reader_mode',
                url      : '/radian/registro-documentos/documentos-radian'
            },
            {
                id       : 'radian-reportes',
                title    : 'Reportes',
                translate: 'NAV.RADIAN_REGISTRO_DOCUMENTOS.REGISTRO_DOCUMENTOS.RADIAN_REPORTES.TITLE',
                icon     : 'file_copy',
                type     : 'collapsable',
                children : [
                    {
                        id       : 'radian-reportes-background',
                        title    : 'Reportes Background',
                        translate: 'NAV.RADIAN_REGISTRO_DOCUMENTOS.REGISTRO_DOCUMENTOS.RADIAN_REPORTES.RADIAN_REPORTES_BACKGROUND.TITLE',
                        type     : 'item',
                        icon     : 'assignment_turned_in',
                        url      : '/radian/registro-documentos/reportes/background'
                    }
                ]
            }
        ]
    },
    {
        id       : 'salir',
        title    : 'Salir',
        translate: 'NAV.SALIR',
        type     : 'item',
        icon     : 'power_settings_new'
    }
];
