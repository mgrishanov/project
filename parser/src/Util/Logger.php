<?php

declare(strict_types=1);

namespace WB\Parser\Util;

use Monolog\Level;
use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

class Logger
{
    private MonologLogger $logger;

    public function __construct(string $name)
    {
        $this->logger = new MonologLogger($name);
        
        // Настройка обработчика для вывода в stdout
//        $stdoutHandler = new StreamHandler('php://stdout', $this->getLogLevel());
//        $stdoutHandler->setFormatter(new LineFormatter(
//            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
//            'Y-m-d H:i:s',
//            true,
//            true
//        ));
//        $this->logger->pushHandler($stdoutHandler);
        
        // Если путь к логам настроен, добавляем файловый обработчик
        if (!empty($_ENV['LOG_PATH'])) {
            $logFile = rtrim($_ENV['LOG_PATH'], '/') . "/{$name}.log";
            $fileHandler = new RotatingFileHandler($logFile, 10, $this->getLogLevel());
            $fileHandler->setFormatter(new LineFormatter(
                "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                'Y-m-d H:i:s',
                true,
                true
            ));
            $this->logger->pushHandler($fileHandler);
        }
    }

    /**
     * Получение уровня логирования из конфигурации
     */
    private function getLogLevel(): Level
    {
        return match (strtolower($_ENV['LOG_LEVEL'] ?? 'info')) {
            'debug' => Level::Debug,
            'info' => Level::Info,
            'notice' => Level::Notice,
            'warning' => Level::Warning,
            'error' => Level::Error,
            'critical' => Level::Critical,
            'alert' => Level::Alert,
            'emergency' => Level::Emergency,
            default => Level::Info,
        };
    }

    /**
     * @param string $message
     * @param array<string, mixed> $context
     */
    public function debug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    /**
     * @param string $message
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    /**
     * @param string $message
     * @param array<string, mixed> $context
     */
    public function notice(string $message, array $context = []): void
    {
        $this->logger->notice($message, $context);
    }

    /**
     * @param string $message
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    /**
     * @param string $message
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    /**
     * @param string $message
     * @param array<string, mixed> $context
     */
    public function critical(string $message, array $context = []): void
    {
        $this->logger->critical($message, $context);
    }

    /**
     * @param string $message
     * @param array<string, mixed> $context
     */
    public function alert(string $message, array $context = []): void
    {
        $this->logger->alert($message, $context);
    }

    /**
     * @param string $message
     * @param array<string, mixed> $context
     */
    public function emergency(string $message, array $context = []): void
    {
        $this->logger->emergency($message, $context);
    }
}
