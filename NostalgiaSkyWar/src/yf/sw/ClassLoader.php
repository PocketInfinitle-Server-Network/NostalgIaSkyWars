<?php
namespace yf\sw;

use IClassLoader;

class ClassLoader implements \IClassLoader
{
	public $pharPath;
	public function loadAll($pharPath)
	{
		$this->pharPath = $pharPath;
		spl_autoload_register([$this, "autoload"]);
	}
	public function autoload($cl){
		if(str_starts_with($cl, "yf\\sw\\")){
			$cl = str_replace("\\", "/", $cl);
			require_once("{$this->pharPath}/src/$cl.php");
		}
	}
}

