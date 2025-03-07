<?php
// Criado por Marcos Peli
// ultima atualização 24/02/2018 - Scripts alterados para utilização do captcha sonoro, unica opção após a atualização da receita com recaptcha do google
// o objetivo dos scripts deste repositório é integrar consultas de CNPJ e CPF diretamente da receita federal
// para dentro de aplicações web que necessitem da resposta destas consultas para proseguirem, como e-comerce e afins.

// inicia sessão
@session_start();

//	define o local onde serão guardados os cookies de sessão , path real e completo
$pasta_cookies = 'cookies/';
define('COOKIELOCAL', str_replace('\\', '/', realpath('./')).'/'.$pasta_cookies);

// Headers comuns em todas as chamadas CURL, com exceçao do Índice [0], que muda para CPF e CNPJ
$headers = array(
	0 => '',	// aqui vai o HOST da consulta conforme a necessidade (CPF ou CNPJ)
	1 => 'User-Agent: Mozilla/5.0 (Windows NT 6.1; rv:53.0) Gecko/20100101 Firefox/53.0',
	2 => 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
	3 => 'Accept-Language: pt-BR,pt;q=0.8,en-US;q=0.5,en;q=0.3',
	4 => 'Connection: keep-alive',
	5 => 'Upgrade-Insecure-Requests: 1'
);	

// urls para obtenção dos dados
$url['cnpj'] = 'https://www.receita.fazenda.gov.br/pessoajuridica/cnpj/cnpjreva/Cnpjreva_solicitacao3.asp';
$url_captcha['cnpj'] = 'https://www.receita.fazenda.gov.br/pessoajuridica/cnpj/cnpjreva/captcha/gerarCaptcha.asp';
$host['cnpj'] = 'Host: www.receita.fazenda.gov.br';

$url['cpf'] = 'http://cpf.receita.fazenda.gov.br/situacao/';
//https://servicos.receita.fazenda.gov.br/Servicos/CPF/ConsultaSituacao/ConsultaPublicaSonoro.asp
//http://cpf.receita.fazenda.gov.br/situacao/defaultSonoro.asp?CPF=&NASCIMENTO=
$url_captcha['cpf'] = 'https://servicos.receita.fazenda.gov.br/Servicos/CPF/ConsultaSituacao/ConsultaPublicaSonoro.asp?CPF=&NASCIMENTO=';
$host['cpf'] =  'Host: servicos.receita.fazenda.gov.br';//cpf.receita.fazenda.gov.br

// percorre os arrays fazendo as chamadas de CNPJ e CPF: $key é o tipo de chamada
foreach ($url as $key => $value)
{
	// define o hosts a ser usado no header da chamada curl conforme $key
	$headers[0] = $host[$key];
	
	// define o nome do arquivo de cookie a ser usado para cada chamada conforme $key
	$cookieFile = COOKIELOCAL.$key.'_'.session_id();
	
	// cria o arquivo se ele não existe
	if(!file_exists($cookieFile))
	{
		$file = fopen($cookieFile, 'w');
		fclose($file);
	}
	else
	{
	
		// pega os dados de sessão gerados na visualização do captcha dentro do cookie
		$file = fopen($cookieFile, 'r');
		$conteudo = '';
		while (!feof($file))
		{$conteudo .= fread($file, 1024);}
		fclose ($file);
	
		$explodir = explode(chr(9),$conteudo);
			
		$sessionName = trim($explodir[count($explodir)-2]);
		$sessionId = trim($explodir[count($explodir)-1]);	
	
		// constroe o parâmetro de sessão que será passado no próximo curl
		$cookie = $sessionName.'='.$sessionId;
	}
	
	$ch = curl_init($value);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FAILONERROR, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
	$result = curl_exec($ch);

	curl_close($ch);
	
	// trata os resultados da consulta curl
	if(!empty($result))
	{
		
		// pega os dados de sessão gerados nas primeiras chamadas e que estão dentro do cookie
		$file = fopen($cookieFile, 'r');
		$conteudo = '';
		while (!feof($file))
		{$conteudo .= fread($file, 1024);}
		fclose ($file);
		
		$explodir = explode(chr(9),$conteudo);
				
		$sessionName = trim($explodir[count($explodir)-2]);
		$sessionId = trim($explodir[count($explodir)-1]);	
		
		// constroe o parâmetro de sessão que será passado no próximo curl
		$cookie = $sessionName.'='.$sessionId;
		
		// faz segunda chamada para pegar o captcha
		$ch = curl_init($url_captcha[$key]);
		
		// continua setando parâmetros da chamada curl
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);		// headers da chamada 
		curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);	// dados do arquivo de cookie
		curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);	// dados do arquivo de cookie
		curl_setopt($ch, CURLOPT_COOKIE, $cookie);			// cookie com os dados da sessão
		curl_setopt($ch, CURLOPT_REFERER, $value);			// refer = url da chamada anterior
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FAILONERROR, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
		$result = curl_exec($ch);
		curl_close($ch);
		
		//print_r($result);
		// extrai resultados conforme $key
		if($key == 'cnpj')
		{$imagem_cnpj = 'data:image/png;base64,'.base64_encode($result);}
		else if($key == 'cpf')
		{

			// Pega Imagem Captcha

			$doc = new DOMDocument();
			@$doc->loadHTML($result);

			$tags = $doc->getElementsByTagName('img');
			$count = 0;
			foreach ($tags as $tag)
			{
				$count++;
				
				if($tag->getAttribute('id') == "imgCaptcha")
				{$imagem_cpf = $tag->getAttribute('src');}

			}

		}
			
	}
	
}
?>
