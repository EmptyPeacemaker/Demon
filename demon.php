<?php
declare(ticks = 1);



class Demon{
    public $signal_name=[
        SIGQUIT=>'sig_handler'
    ];
    public $is_loop=true;
    public $base_dir;
    public function __construct()
    {
        $this->base_dir = dirname(__FILE__) . '/tmp';


        $this->delTree($this->base_dir);
        if (!mkdir($this->base_dir) && !is_dir($this->base_dir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $this->base_dir));
        }

        fwrite(fopen($this->base_dir . '/pid_demon.log', 'a'), getmypid());

        foreach ($this->signal_name as $index => $item) {
            pcntl_signal($index,array($this,$item));
        }
    }
    public function console(){
        global $STDIN,$STDOUT,$STDERR;
        fclose(STDIN);
        fclose(STDOUT);
        fclose(STDERR);

        $STDIN = fopen('/dev/null', 'r');
        $STDOUT = fopen($this->base_dir . '/application.log', 'ab');
        $STDERR = fopen($this->base_dir . '/daemon.log', 'ab');
    }
    private function delTree($dir)
    {
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->delTree("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }
    public function sig_handler($signal){
        switch ($signal){
            case SIGQUIT:
                $this->is_loop=false;
                break;
        }
    }
    public function run(){
        $this->console();

        while ($this->is_loop){

            sleep(1);
        }
    }
}

class Manager{
    private $file_path;
    public function __construct($patch)
    {
        $this->file_path=$patch.'/pid_demon.log';
    }

    public function start(){
        $pid = pcntl_fork();
        if ($pid === -1) {
            echo "[!] Error fork process" . PHP_EOL;
            die();
        }
        if ($pid) {
            echo '[+] Demon running' . PHP_EOL;
            die();
        }
        (new Demon())->run();
    }

    public function stop()
    {
        posix_kill(fread(fopen($this->file_path,'r'),filesize($this->file_path)), SIGQUIT);
    }
    

}


$manager=new Manager(dirname(__FILE__).'/tmp');
if(isset($argv[1])){
    switch ($argv[1]){
        case 'start':
            $manager->start();
            break;
        case 'stop':
            $manager->stop();
            break;
    }
}
