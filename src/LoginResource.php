<?php
namespace happybt\coop;

use watoki\curir\Resource;
use watoki\curir\responder\Redirecter;
use watoki\factory\Factory;

class LoginResource extends Resource {

    private $dataDir;

    /**
     * @param Factory $factory <-
     */
    public function __construct(Factory $factory) {
        parent::__construct($factory);
        $this->dataDir = __DIR__ . '/../user/data/';
    }

    public function doGet() {
        return [];
    }

    public function doPost($user, $password) {
        $site = json_decode(file_get_contents($this->dataDir . '../site.json'), true);

        if ($user == $site['user'] && $password == $site['password']) {
            session_start();
            $_SESSION['loggedin'] = true;

            return Redirecter::fromString('admin/');
        }

        return [];
    }
}