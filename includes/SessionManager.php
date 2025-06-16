<?php
class SessionManager
{
    private $timeout = 600; 

    public function start()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function checkTimeout()
    {
        $this->start();

        if (!isset($_SESSION['last_activity'])) {
            $_SESSION['last_activity'] = time();
        }

        if (time() - $_SESSION['last_activity'] > $this->timeout) {
            $this->destroy();
            header("Location: ../authentication/logout.php?timeout=1");
            exit;
        }

        $_SESSION['last_activity'] = time(); 
    }

    public function destroy()
    {
        $this->start();
        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                      $params["path"], $params["domain"],
                      $params["secure"], $params["httponly"]);
        }

        session_destroy();
    }
}