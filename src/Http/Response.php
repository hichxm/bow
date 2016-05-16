<?php

namespace Bow\Http;

use ErrorException;
use Jade\Jade;
use Twig_Environment;
use Twig_Loader_Filesystem;
use Bow\Core\AppConfiguration;
use Bow\Exception\ViewException;

class Response
{
	/**
     * Singleton
     * @var self
     */
    private static $instance = null;

	/**
	 * Liste de code http valide pour l'application
	 * Sauf que l'utilisateur poura lui même rédéfinir
	 * ces codes s'il utilise la fonction `header` de php
	 */
	private static $header = [
		200 => "OK",
		201 => "Created",
		202 => "Accepted",
		204 => "No Content",
		205 => "Reset Content",
		206 => "Partial Content",
		300 => "Multipe Choices",
		301 => "Moved Permanently",
		302 => "Found",
		303 => "See Other",
		304 => "Not Modified",
		305 => "Use Proxy",
		307 => "Temporary Redirect",
		400 => "Bad Request",
		401 => "Unauthorized",
		402 => "Payment Required",
		403 => "Forbidden",
		404 => "Not Found",
		405 => "Method Not Allowed",
		406 => "Not Acceptable",
		407 => "Proxy Authentication",
		408 => "Request Time Out",
		409 => "Conflict",
		410 => "Gone",
		411 => "Length Required",
		412 => "Precondition Failed",
		413 => "Payload Too Large",
		414 => "URI Too Long",
		415 => "Unsupport Media",
		416 => "Range Not Statisfiable",
		417 => "Expectation Failed",
		500 => "Internal Server Error",
		501	=> "Not Implemented",
		502	=> "Bad Gateway",
		503 => "Service Unavailable",
		504	=> "Gateway Timeout",
		505	=> "HTTP Version Not Supported",
	];

	/**
     * Instance de l'application
     * @var AppConfiguration
     */
    private $config;

    private function __construct(AppConfiguration $appConfig)
    {
        $this->config = $appConfig;
    }

    /**
     * Singleton loader
     * 
     * @param AppConfiguration $appConfig
     * @return self
     */
    public static function configure(AppConfiguration $appConfig)
    {
        if (self::$instance === null) {
            self::$instance = new self($appConfig);
        }

        return self::$instance;
    }

	/**
	 * @return Response
	 */
	public static function takeInstance()
	{
		return self::$instance;
	}

	/**
	 * Modifie les entêtes http
	 *
	 * @param string $key
	 * @param string $value
	 * @return self
	 */
	public function set($key, $value)
	{
		header("$key: $value");

		return $this;
	}
    
    /**
     * redirect, permet de lancer une redirection vers l'url passé en paramêtre
     *
     * @param string $path
     */
    public function redirect($path)
    {
		header("Location: " . $path, true, 301);
		echo '<a href="' . $path . '" >' . self::$header[301] . '</a>';

		die();
    }

    /**
     * redirectTo404, rédirige vers 404
     * @return self
     */
    public function redirectTo404()
    {
        $this->code(404);
        return $this;
    }

	/**
	 * Modifie les entétes http
	 * 
	 * @param int $code
	 * @param bool $override
	 * @return bool|void
	 */
	public function code($code, $override = false)
	{
		$r = true;

		if (in_array((int) $code, array_keys(self::$header), true)) {
			header("HTTP/1.1 $code " . self::$header[$code], $override, $code);
		} else {
			$r = false;
		}

		return $r;
	}

	/**
	 * Réponse de type JSON
	 *
	 * @param mixed $data
	 * @param int $code
	 * @param bool $end
	 */
	public function json($data, $code = 200, $end = false)
	{
		if (is_bool($code)) {
            $end = $code;
            $code = 200;
        };

		$this->set("Content-Type", "application/json; charset=UTF-8");
		$this->code($code);
		$this->send(json_encode($data), $end);
	}

	/**
	 * sendFile, require $filename
	 * 
	 * @param string $filename
	 * @param array $bind
	 * @throws ViewException
	 * @return mixed
	 */
	public function sendFile($filename, $bind = [])
	{
		$filename = preg_replace("/@|#|\./", "/", $filename);

		if ($this->config->getViewpath() !== null) {
			$tmp = $this->config->getViewpath() ."/". $filename . ".php";
			if (!file_exists($tmp)) {
				$filename = $this->config->getViewpath() ."/". $filename . ".html";			
			} else {
				$filename = $tmp;
			}
		}

		if (!file_exists($filename)) {
			throw new ViewException("La vue $filename n'exist pas.", E_ERROR);
		}

 		extract($bind);
		// Rendu du fichier demandé.

		return require $filename;
	}

	/**
	 * render, lance le rendu utilisant le template définir <<mustache|twig|jade>>
	 *
	 * @param string $filename
	 * @param array $bind
	 * @param integer $code=200
	 * @throws ViewException
	 * @return self
	 */
	public function view($filename, $bind = null, $code = 200)
	{
		$filename = preg_replace("/@|\.|#/", "/", $filename) . $this->config->getTemplateExtension();

		if ($this->config->getViewpath() !== null) {
			if (!is_file($this->config->getViewpath() . "/" . $filename)) {
				throw new ViewException("La vue $filename n'exist pas!.", E_ERROR);
			}
		} else {
			if (!is_file($filename)) {
				throw new ViewException("La vue $filename n'exist pas!.", E_ERROR);
			}
		}

		if ($bind === null) {
			$bind = [];
		}

		// Chargement du template.
		$template = $this->templateLoader();
		$this->code($code);

		if ($this->config->getEngine() == "twig") {
			$this->send($template->render($filename, $bind));
		} else if (in_array($this->config->getEngine(), ["mustache", "jade"])) {
			$this->send($template->render(file_get_contents($filename), $bind));
		}

		exit();
	}

	/**
	 * templateLoader, charge le moteur template à utiliser.
	 * 
	 * @throws ErrorException
	 * @return Twig_Environment|\Mustache_Engine|Jade
	 */
	private function templateLoader()
	{
		if ($this->config->getEngine() === null) {
			if (!in_array($this->config->getEngine(), ["twig", "mustache", "jade"])) {
				throw new ErrorException("Erreur: template n'est pas définir");
			}
		}

		$tpl = null;

		if ($this->config->getEngine() == "twig") {
		    $loader = new Twig_Loader_Filesystem($this->config->getViewpath());
		    $tpl = new Twig_Environment($loader, [
		        'cache' => $this->config->getCachepath(),
				'auto_reload' => $this->config->getCacheAutoReload(),
                "debug" => $this->config->getLogLevel() == "develepment" ? true : false
		    ]);
		} else if ($this->config->getEngine() == "mustache") {
			$tpl = new \Mustache_Engine([
                'cache' => $this->config->getCachepath()
			]);
		} else {
			$tpl = new Jade([
                'cache' => $this->config->getCachepath(),
                'prettyprint' => true,
                'extension' => $this->config->getTemplateExtension()
            ]);
		}

		return $tpl;
	}

	/**
	 * Equivalant à un echo, sauf qu'il termine l'application quand $stop = true
	 *
	 * @param string|array|\StdClass $data
	 * @param bool|false $stop
	 */
	public function send($data, $stop = false)
	{
		if (is_array($data) || ($data instanceof \StdClass)) {
			$data = json_encode($data);
		}

		echo $data;

		if ($stop) {
			die();
		}
	}
}
