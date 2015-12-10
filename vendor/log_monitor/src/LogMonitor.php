<?php
class LogMonitor
{
    const MON_SAVE_PATH = '/tmp/';

    protected $logFile = '';

    protected $logFileMd5 = '';

    protected $monPointFile = '';

    protected $monDiffFile = '';

    protected $lastPoint = 1;

    protected $newPoint = 1;

    protected $mailTitle = '【警告】服务器 %s 产生 监控日志 %d 条';

    protected $mailSender = 'monitor@xxx.com';

    protected $mailSenderPwd = '******';

    protected $mailSenderName = 'Monitor';

    protected $mailHost = 'smtp.mxhichina.com';

    protected $smtpSecure = 'ssl';

    protected $mailPort = 465;

    protected $monitorRules = array(
        array(
            'rule_name' => 'PHP Fatal',
            'regex' => 'PHP Fatal error',
        ),
        array(
            'rule_name' => 'Mysql Fatal',
            'regex' => 'SQLSTATE',
        ),
        array(
            'rule_name' => 'Alipay Notice Warning',
            'regex' => 'WARNING: alipay notice',
        ),
        array(
            'rule_name' => 'WxPay Notice Warning',
            'regex' => 'WARNING: wechat notice',
        ),
        array(
            'rule_name' => 'Alipay Recharge Notice Warning',
            'regex' => 'WARNING: alipay recharge notice',
        ),
        array(
            'rule_name' => 'WxPay Recharge Notice Warning',
            'regex' => 'WARNING: wechat recharge notice',
        ),
        array(
            'rule_name' => 'User Register Warning',
            'regex' => 'WARNING: user register failed',
        ),
    );

    protected $notify = array(
        'mail' => array(
            'dev-backend@xxx.com',
            'qa@xxx.com',
        ),
        'lesstalk' => 'https://hook.lesschat.com/incoming/xxxxx',
    );

    public function __construct($logFile)
    {
        echo "monitor start at ".date('Y-m-d H:i:s').". log_file[".$logFile."]\n";
        $this->logFile = $logFile;
        $this->logFileMd5 = md5($logFile);
        $this->monPointFile = self::MON_SAVE_PATH.'log_monitor_point.'.$this->logFileMd5;
        $this->monDiffFile = self::MON_SAVE_PATH.'log_monitor_diff.'.$this->logFileMd5;
    }

    public function run()
    {
        $this->lastPoint = intval(trim(@file_get_contents($this->monPointFile)));
        empty($this->lastPoint) && $this->lastPoint = 1;

        $this->newPoint = intval(shell_exec("wc -l ".$this->logFile." | awk '{print $1}'"));
        empty($this->newPoint) && $this->newPoint = 1;
        if ($this->newPoint == $this->lastPoint) {
            return;
        }

        $this->newPoint < $this->lastPoint && $this->lastPoint = 1;

        if (empty($this->monitorRules)) {
            echo "no monitor rules.\n";
            return;
        }

        foreach ($this->monitorRules as $rule) {
            $output = shell_exec('sed -n "'.$this->lastPoint.','.$this->newPoint.'p" '.$this->logFile.' | grep -P "'.$rule['regex'].'" > '.$this->monDiffFile);
            $this->notify($rule);
        }
        
        file_put_contents($this->monPointFile, $this->newPoint);
    }

    protected function notify($rule)
    {
        $rows = intval(shell_exec("wc -l ".$this->monDiffFile." | awk '{print $1}'"));
        echo "\"{$rule['rule_name']}\" matching {$rows} log.\n";

        if (empty($this->notify)) {
            echo "no notify rules.\n";
            return;
        }

        if ($rows > 0) {
            foreach ($this->notify as $func => $to) {
                $func = 'send'.ucfirst($func);
                if (!method_exists($this, $func)) {
                    echo "{$func} method not exists.\n";
                    continue;
                }

                $this->$func($to, $rows, $rule);
            }
        }
    } 

    protected function sendMail($recipients, $rows, $rule)
    {
        $mailBody  = '监控日志：'.$this->logFile."<br>";
        $mailBody .= '监控规则：'.$rule['regex']."<br>";
        $mailBody .= @file_get_contents($this->monDiffFile); //mail body 
        $subject   = sprintf($this->mailTitle, trim(shell_exec('hostname')), $rows); //subject 

        $mail = new PHPMailer;

        $mail->isSMTP();                                      // Set mailer to use SMTP
        $mail->Host = $this->mailHost;                        // Specify main and backup SMTP servers
        $mail->SMTPAuth = true;                               // Enable SMTP authentication
        $mail->Username = $this->mailSender;                  // SMTP username
        $mail->Password = $this->mailSenderPwd;               // SMTP password
        $mail->SMTPSecure = $this->smtpSecure;                // Enable TLS encryption, `ssl` also accepted
        $mail->Port = $this->mailPort;                        // TCP port to connect to

        $mail->setFrom($this->mailSender, $this->mailSenderName);
        foreach ($recipients as $to) {
            $mail->addAddress($to);
        }
        $mail->isHTML(true);                                  // Set email format to HTML
        $mail->Subject = $subject;
        $mail->Body    = $mailBody;
        $mail->CharSet = 'utf-8';

        if(!$mail->send()) {
            echo 'Mailer Error: ' . $mail->ErrorInfo;
        }
    }

    protected function sendLesstalk($to, $rows, $rule)
    {
        $content = array(
            'title' => 'php_error',
            'text'  => file_get_contents($this->monDiffFile)
        );
        $content = json_encode($content);
        $output = shell_exec("echo '".$content."' | curl -X POST -H \"Content-Type: application/json\" -d @- ".$to);
    }

    public function __destruct()
    {
        echo "monitor end at ".date('Y-m-d H:i:s').". log_file[".$this->logFile."] lastPoint[".$this->lastPoint."] newPoint[".$this->newPoint."]\n";
    }
}

