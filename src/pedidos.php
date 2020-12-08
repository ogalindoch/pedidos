<?php

namespace euroglas\pedidos;

class pedidos implements \euroglas\eurorest\restModuleInterface
{
    // Nombre oficial del modulo
    public function name() { return "pedidos"; }

    // Descripcion del modulo
    public function description() { return "Acceso a los pedidos en Mordor"; }

    // Regresa un arreglo con los permisos del modulo
    // (Si el modulo no define permisos, debe regresar un arreglo vacío)
    public function permisos()
    {
        $permisos = array();

        // $permisos['_test_'] = 'Permiso para pruebas';

        return $permisos;
    }

    // Regresa un arreglo con las rutas del modulo
    public function rutas()
    {
        $items['/pedidos']['GET'] = array(
            'name' => 'Lista pedidos',
            'callback' => 'listapedidos',
            'token_required' => TRUE,
        );

        $items['/pedidos/']['GET'] = array(
            'name' => 'Lista pedidos/',
            'callback' => 'listapedidos',
            'token_required' => TRUE,
        );

        $items['/pedidos/[i:id]']['GET'] = array(
            'name' => 'Un pedido',
            'callback' => 'pedido',
            'token_required' => TRUE,
        );

        return $items;
    }

        /**
     * Define que secciones de configuracion requiere
     *
     * @return array Lista de secciones requeridas
     */
    public function requiereConfig()
    {
        $secciones = array();

        $secciones[] = 'dbaccess';

        return $secciones;
    }

    private $config = array();

    /**
     * Carga UNA seccion de configuración
     *
     * Esta función será llamada por cada seccion que indique "requiereConfig()"
     *
     * @param string $sectionName Nombre de la sección de configuración
     * @param array $config Arreglo con la configuracion que corresponde a la seccion indicada
     *
     */
    public function cargaConfig($sectionName, $config)
    {
        $this->config[$sectionName] = $config;
        if ($sectionName == 'dbaccess')
        {
            $this->connect_db();
        }
    }

    private function connect_db()
    {
        //static::$configFile = $this->config['dbaccess']['config'];
        //if (static::$configFile != '')
        if ($this->config && $this->config['dbaccess'])
        {
            //$this->dbRing = new \euroglas\dbaccess\dbaccess(static::$configFile);
            $this->dbRing = new \euroglas\dbaccess\dbaccess($this->config['dbaccess']['config']);

            if( $this->dbRing->connect('TheRing') === false )
            {
                print($this->dbRing->getLastError());
            }
        }
    }

    function __construct()
    {
        $this->DEBUG = isset($_REQUEST['debug']);
    }

    public function listapedidos() {
        /// TODO: Validar permisos
        /*
        if( FALSE === ElUsuario::TienePermiso('ver pedidos') )
        {
            http_response_code(403); // 403 Forbidden
            header('content-type: application/json');
            $user = ElUsuario::getInstance();
            $nombre = $user->loginName();

            die(json_encode(
                reportaErrorUsandoHateoas(
                    403,
                    'Acceso no autorizado',
                    "El usuario actual [{$nombre}] no tiene suficientes permisos para 'ver pedidos'."
                ))
            );
        }
        */
        // $pedidos = new ClassPedidos();
        $pagina = 1;
        $filtro = array();
        $cols = array();
        $origen = array();
        $numPedido = null;

        if(isset($_REQUEST['page']))
        {
            $pagina = $_REQUEST['page'];
        }
        if(isset($_REQUEST['pageSize']))
        {
            $this->pedidosPorPagina = $_REQUEST['pageSize'];
        }
        if(isset($_REQUEST['filter']))
        {
            $filtro = urldecode( $_REQUEST['filter'] );
            $filtro = $this->validaColumnas($filtro);
            if($filtro===false)
            {
                http_response_code(400); // 400 Bad Request
                header('content-type: application/json');
                die(json_encode(
                    reportaErrorUsandoHateoas(
                        400,
                        'Invalid filter',
                        'El parametro filter contiene caracteres invalidos',
                        $_REQUEST['filter']
                    ))
                );
            }
            $filtro = explode(',',$filtro );
        }
        if(isset($_REQUEST['columns']))
        {
            $queryCols = urldecode( $_REQUEST['columns'] );
            $queryCols = $this->validaColumnas($queryCols); // prevent SQL injection
            if($queryCols===false)
            {
                http_response_code(400); // 400 Bad Request
                header('content-type: application/json');
                die(json_encode(
                    reportaErrorUsandoHateoas(
                        400,
                        'Invalid Columns',
                        'El parametro columns contiene caracteres invalidos'
                    ))
                );

            }
            //print("QueryCols = $queryCols\n");
            $cols = explode(',',$queryCols);
        }
        if(isset($_REQUEST['origenDeSurtido']))
        {
            $queryOrigen = urldecode( $_REQUEST['origenDeSurtido'] );
            $queryOrigen = $this->validaColumnas($queryOrigen); // prevent SQL injection
            if($queryOrigen===false)
            {
                http_response_code(400); // 400 Bad Request
                header('content-type: application/json');
                die(json_encode(
                    reportaErrorUsandoHateoas(
                        400,
                        'Invalid origenDeSurtido',
                        'El parametro origenDeSurtido contiene caracteres invalidos'
                    ))
                );

            }
            //print("QueryCols = $queryCols\n");
            $origen = explode(',',$queryOrigen);
        }

        if(isset($_REQUEST['numPedido']))
        {
            $numPedido = $_REQUEST['numPedido'];
        }

        $this->filtro = $filtro;
        $this->cols = $cols;
        $this->origenDeSurtido = $origen;
        $losPedidos = $this->getPedidos($pagina,$numPedido);

        die( $this->formateaRespuesta( $losPedidos ) );

    }

    public function pedido($id=-1) {
        /*
        if( FALSE === ElUsuario::TienePermiso('ver pedidos') )
        {
            http_response_code(403); // 403 Forbidden
            header('content-type: application/json');
            die(json_encode(
                reportaErrorUsandoHateoas(
                    403,
                    'Acceso no autorizado',
                    'El usuario actual no tiene suficientes permisos para ver pedidos.'
                ))
            );
        }
        */

        //global $loader, $twig;
        //$pedidos = new ClassPedidos();

        $elPedido = $this->getPedido($id);

        if( $elPedido === false )
        {
            http_response_code(404); // 404 Not Found
            header('content-type: application/json');
            die(json_encode(
                reportaErrorUsandoHateoas(
                    404,
                    'Ese pedido no existe',
                    'El pedido solicitado no fue encontrado en el servidor'
                ))
            );
        }

        if(
            (isset( $_SERVER['HTTP_ACCEPT'] ) && stripos($_SERVER['HTTP_ACCEPT'], 'json')!==false)
            || (isset( $_REQUEST['format'] ) && stripos($_REQUEST['format'], 'json')!==false)

        )
        {
            header('content-type: application/json');

            //print('<pre>');print_r($elPedido);print('</pre>');
            $elPedido['NotaPedido'] = addcslashes($elPedido['NotaPedido'], "\r\n\t");

            //echo $twig->render('pedido.json', $elPedido ) ;
            //echo $twig->render('pedido.json', addcslashes( $elPedido, "\r\n" ) ) ;

            die( json_encode( $elPedido ) );
        }
        else
        {
            // Formato no definido
            header('content-type: text/plain');
            //print_r($_REQUEST);
            die( print_r($elPedido,true) );
        }
    }

    function validaColumnas($string)
    {
        //return preg_replace('/[^a-zA-Z0-9,\s]/', '', $string); este filtro permite espacios
        //print("Validando [{$string}]\n");
        return( filter_var(
                    $string,
                    FILTER_VALIDATE_REGEXP,
                    array(
                        "options"=>array("regexp"=>'/^[\w\s.,\-]+$/')
                    )
                )
        );
    }
    public function getPedidos($page=null,$numPedido=null)
	{
		//global $DEBUG;
		if($page==null) $page = 1;

		if( count($this->filtro) > 0 )
		{
		    $verPendientes=in_array( 'pendientes', $this->filtro );
		    $verAutorizados=in_array( 'autorizados', $this->filtro );
		    $verRechazados=in_array( 'rechazados', $this->filtro );
		    $verCancelados=in_array( 'cancelados', $this->filtro );
		}

		$rowOffset = ($page-1) * $this->pedidosPorPagina;

		$query = '';
		$query .= 'SELECT estatusDelPedido(idPedido) AS estatus, ';
		if( count($this->cols) > 0 )
		{
			//print("limitando columnas");
			$dbCols = implode(',', $this->cols);
			$query .= $dbCols;
		}
		else
		{
			$query .= " idPedido, DATE_FORMAT(Fecha, '%Y-%m-%dT%TZ') AS Fecha, PedidoDeEmpresa, OrdenDeCompra, PedidoDeCliente, cliente.Nombre, pedido.InicialesVendedor, InicialesQuienHacePedido, pedido.NotaPedido, Origen, OrdenDeCompra, AutorizoCredito, QuienAutorizoEnCxC, CuandoAutorizoEnCxC, Cancelada, NotaPedido, NotaCredito, NotaCancelado, Destino, idContenedorParaSurtir ";
			//if( count($this->origenDeSurtido) > 0 )
			{
				$query .= ', COALESCE(contenedorplanta.Nombre, "") AS origenDeSurtido ';
			}
		}
		$query .= ' FROM pedido ';
		$ClienteLlave =
		//$query .= '  JOIN cliente ON (pedido.PedidoDeCliente = cliente.CodigoSAE AND pedido.PedidoDeEmpresa = cliente.ClienteDe) ';
		$query .= '  JOIN cliente ON ( CONCAT(pedido.PedidoDeEmpresa,pedido.PedidoDeCliente) = cliente.Llave ) ';
		//if( count($this->origenDeSurtido) > 0 )
		{
			$query .= '  LEFT JOIN contenedorplanta ON ( contenedorplanta.idContenedor = pedido.idContenedorParaSurtir ) ';
		}
		$query .= ' WHERE 1=1 ';
		if( count($this->origenDeSurtido) > 0 )
		{
			$lista = "'".implode("','",$this->origenDeSurtido)."'";
			$query .= " AND contenedorplanta.Nombre in ({$lista}) ";
		}
		if( count($this->filtro) > 0 )
		{
			$query .= 'AND ( 1=2 '; // "truco" para que todas condiciones sean "OR ..."

			if($verPendientes) // RevisadoPorCxc = 0
			{
				$query .= 'OR (QuienAutorizoEnCxC IS NULL AND (Cancelada=0 OR Cancelada IS NULL)) '; // Pendientes, los que no han sido revisados y no estan cancelados
			}

			if($verAutorizados) // Autorizado = 1 && Cancelado = 0
			{
				$query .= 'OR (AutorizoCredito=1 AND (Cancelada=0 OR Cancelada IS NULL)) '; // Autorizado no cancelado
			}

			if($verRechazados) //
			{
				$query .= 'OR (QuienAutorizoEnCxC IS NOT NULL AND (AutorizoCredito=0 OR AutorizoCredito IS NULL) AND (Cancelada=0 OR Cancelada IS NULL)) '; // Revisados por CxC y no estan autorizados ni cancelados
			}

			if($verCancelados) // Cancelado = 1
			{
				$query .= 'OR (Cancelada=1) '; // Canceladas
			}

			$query .= ' ) ';
		}

		if( $numPedido !== null )
		{
			$query .= ' AND idPedido IN (?)  ';
		}
		$query .= '  ORDER BY idPedido DESC';

		$query .= "  LIMIT {$this->pedidosPorPagina} OFFSET {$rowOffset}";

		$this->dbRing->prepare($query, 'queryGetPedidos');

		if( $numPedido !== null )
		{
			$sth = $this->dbRing->execute('queryGetPedidos',array($numPedido));
		}
		else
		{
			$sth = $this->dbRing->execute('queryGetPedidos');
		}

		if( $sth === false )
		{
			//print('<pre>');print_r($query);print('</pre>');
			return $this->dbRing->getLastError();
		}
		//print_r($sth);

		$datosDePedidos = array();

		while(	$datosDelPedido = $sth->fetch(\PDO::FETCH_ASSOC) )
		{
			$datosDelPedido['links'] = array('self'=>"/pedidos/{$datosDelPedido['idPedido']}");

            if($this->DEBUG) {
                $datosDelPedido['debug']['pedido']['query'] = array('raw'=>$query);
                $datosDelPedido['debug']['pedido']['parametros'] = array(
                    'pedidosPorPagina' => $this->pedidosPorPagina,
                    'filtro' => $this->filtro,
                    'cols' => $this->cols,
                    'origenDeSurtido' => $this->origenDeSurtido
                );
            }

			$datosDePedidos[] = $datosDelPedido;
		}

		//if( count($datosDePedidos) == 0 ) return false;

		return $datosDePedidos;
	}

    private function getPedido($idPedido,$conPartidas=TRUE)
	{
		//$ArrayDatosDelPedido = $this->getPedidos(null,null,null,$idPedido);
		$ArrayDatosDelPedido = $this->getPedidos(null,$idPedido);
		if( count($ArrayDatosDelPedido) == 0 ) return false;
		$datosDelPedido = $ArrayDatosDelPedido[0];

		if( ! $this->dbRing->queryPrepared('queryGetPedidoPartidas') )
		{
			$query = '';
			$query .= 'SELECT ';
			$query .= '  pp.SKU, ';
			//$query .= '  p.Clave, ';
			$query .= '  p.Descripcion, ';
			$query .= '  p.Linea, ';
			$query .= '  p.ClaveTipo, ';
			$query .= '  pp.Cantidad, ';
			$query .= '  pp.PrecioSinDescuento, ';
			$query .= '  pp.Descuento, ';
			$query .= '  pp.Precio, ';
			$query .= '  pp.Moneda, ';
			$query .= '  pp.Promos ';
			$query .= ' FROM pedidopartidas pp ';
			$query .= ' LEFT JOIN variantes v ON (v.SKU = pp.SKU) ';
			$query .= ' LEFT JOIN producto p ON (v.idProducto = p.idProducto) ';
			$query .= ' WHERE pp.idPedido = ?';

			$this->dbRing->prepare($query, 'queryGetPedidoPartidas');
		}

		$sth = $this->dbRing->execute('queryGetPedidoPartidas',array($idPedido));
		$partidasDelPedido = $sth->fetchAll(\PDO::FETCH_ASSOC);

		// Un pedido sin partidas, no es pedido... esto NO debería ocurrir
		if( count($partidasDelPedido) == 0 ) return false;

		//print_r($datosDelPedido);
		$datosDelPedido['Partidas'] = $partidasDelPedido;

        if($this->DEBUG) {
            $datosDelPedido['debug']['pedido partidas']['query'] = array(
                'raw'=>$this->dbRing->rawQueryPrepared('queryGetPedidoPartidas'),
                'values' => $idPedido
            );
            $datosDelPedido['debug']['pedido partidas']['parametros'] = array(
                'pedidosPorPagina' => $this->pedidosPorPagina,
                'filtro' => $this->filtro
            );
            $datosDelPedido['debug']['pedido partidas']['count'] =count($partidasDelPedido);
        }

		//print_r( $result );
		return($datosDelPedido);
	}

    private function formateaRespuesta($datos)
	{
		if(
			(isset( $_SERVER['HTTP_ACCEPT'] ) && stripos($_SERVER['HTTP_ACCEPT'], 'JSON')!==false)
			||
			(isset( $_REQUEST['format'] ) && stripos($_REQUEST['format'], 'JSON')!==false)
		)
		{
			header('content-type: application/json');
			return( json_encode( $datos ) );
		}
		else if(
			(isset( $_SERVER['HTTP_ACCEPT'] ) && stripos($_SERVER['HTTP_ACCEPT'], 'CSV')!==false)
			||
			(isset( $_REQUEST['format'] ) && stripos($_REQUEST['format'], 'CSV')!==false)
		)
		{
			$output = fopen("php://output",'w') or die("Can't open php://output");
			header("Content-Type:application/csv");
			foreach($datos as $dato) {
				if(is_array($dato))
				{
    				fputcsv($output, $dato);
				} else {
					fputs($output, $dato . "\n");
				}
			}
			fclose($output) or die("Can't close php://output");
			return;
			//return( json_encode( $datos ) );
		}
		else
		{
			// Formato no definido
			header('content-type: text/plain');
			return( print_r($datos, TRUE) );
		}
    }

    private $pedidosPorPagina = 10;
	private $filtro = array();
	private $cols=array();
	private $origenDeSurtido = array();
	private $pedidosPorPaginaDefault = 10;
	private $dbRing;
    private $DEBUG = false;
}