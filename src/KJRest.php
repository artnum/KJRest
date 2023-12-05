<?php
namespace artnum\KJRest;

use Exception;
use Error;
class KJRest {
    protected HTTP\IRequest $request;
    protected string $endpointBasePath;
    protected IEndpoint $endpoint;
    protected DBWallet $dbwallet;
    protected Configuration\IConfiguration $config;
    function __construct(string $endpointBasePath, Configuration\IConfiguration $config, HTTP\IRequest $request)
    {
        $this->endpointBasePath = $endpointBasePath;
        $this->request = $request;
        $this->config = $config;
    }

    function error(Exception $e): void {
        ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($e->getCode());
        $body = [
            'data' => [],
            'lenght' => 0,
            'success' => false,
            'error' => $e->getMessage(),
            'stack' => []
        ];
        while (($e = $e->getPrevious()) !== null) {
            $body['stack'][] = [$e->getMessage(), $e->getCode()];
        }
        echo json_encode($body);
        exit;
    }

    function init(): void {
        try {
            ob_start();
            header('Content-Type: application/json; charset=utf-8');

            $this->dbwallet = new DBWallet();

            $endpoint = $this->request->getEndpoint();
            if ($endpoint === null) {
                $this->error(new Exception('Endpoint not found 1', 404));
            }
            $includeFile = $this->endpointBasePath . '/' . $endpoint . '.php';
            if (!file_exists($includeFile)) {
                $this->error(new Exception('Endpoint not found', 404));
            }
            try {
                include($includeFile);
                $this->endpoint = new $endpoint($this->request);
                $this->endpoint->init();
            } catch (Error|Exception $e) {
                $this->error(new Exception('Endpoint not found 2', 404, $e));
            }
        } catch (Exception $e) {
            $this->error(new Exception('Server error', 500, $e));
        }
    }

    function run(): void {
        try {
            echo '{ "data": [';
            $count = 0;
            foreach($this->endpoint->run($this->dbwallet) as $result) {
                if ($count > 0) { echo ','; }
                echo json_encode($result);
                $count++;
            }
            echo '], "length": ' . $count . ', "success": true, "error": null, "stack": [] }';
        } catch (Exception $e) {
            $this->error(new Exception('Server error', 500, $e));
        }
    }

}