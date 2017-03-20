<?php

namespace Concerto\PanelBundle\Service;

use Concerto\PanelBundle\Entity\AdministrationSetting;
use Concerto\PanelBundle\Entity\Message;
use Concerto\PanelBundle\Repository\AdministrationSettingRepository;
use Concerto\PanelBundle\Repository\MessageRepository;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Templating\EngineInterface;
use Concerto\PanelBundle\Entity\TestSession;
use Concerto\PanelBundle\Repository\TestSessionLogRepository;
use Concerto\PanelBundle\Entity\TestSessionLog;

class AdministrationService {

    private $settingsRepository;
    private $messagesRepository;
    private $authorizationChecker;
    private $configSettings;
    private $templating;
    private $testSessionLogRepository;

    public function __construct(AdministrationSettingRepository $settingsRepository, MessageRepository $messageRepository, AuthorizationChecker $authorizationChecker, $configSettings, EngineInterface $templating, TestSessionLogRepository $testSessionLogRepository) {
        $this->settingsRepository = $settingsRepository;
        $this->messagesRepository = $messageRepository;
        $this->authorizationChecker = $authorizationChecker;
        $this->configSettings = $configSettings;
        $this->templating = $templating;
        $this->testSessionLogRepository = $testSessionLogRepository;
    }

    public function insertSessionLimitMessage(TestSession $session) {
        $msg = new Message();
        $msg->setCagegory(Message::CATEGORY_SYSTEM);
        $msg->setSubject("Session limit reached.");
        $content = $this->templating->render("ConcertoPanelBundle:Administration:msg_session_limit.html.twig", array(
            "limit" => $this->getSessionLimit()
        ));
        $msg->setMessage($content);
        $this->messagesRepository->save($msg);
    }

    private function fetchTestSessionLogs($start_time) {
        foreach ($this->testSessionLogRepository->findAllNewerThan($start_time) as $log) {
            $msg = new Message();
            $msg->setTime($log->getCreated());
            $msg->setCagegory(Message::CATEGORY_TEST);
            $error_source = "";
            switch ($log->getType()) {
                case TestSessionLog::TYPE_JS: $error_source = "JS";
                    break;
                case TestSessionLog::TYPE_R: $error_source = "R";
                    break;
            }
            $msg->setSubject("Test #" . $log->getTest()->getId() . ", $error_source error.");
            $content = $this->templating->render("ConcertoPanelBundle:Administration:msg_test_error.html.twig", array(
                "test_id" => $log->getTest()->getId(),
                "error_source" => $error_source,
                "error_message" => $log->getMessage()
            ));
            $msg->setMessage($content);
            $this->messagesRepository->save($msg);
        }
    }

    public function fetchMessagesCollection() {
        $last_fetch_time = $this->getLastMessageFetchTime();
        if ($last_fetch_time === null)
            $last_fetch_time = time() - 60 * 60 * 24 * 7;

        $this->fetchTestSessionLogs($last_fetch_time);

        $this->setSettings(array("last_message_fetch_time" => time()));
    }

    public function getMessagesCollection() {
        $this->fetchMessagesCollection();
        return $this->messagesRepository->findAll();
    }

    public function deleteMessage($object_ids) {
        $object_ids = explode(",", $object_ids);
        $this->messagesRepository->deleteById($object_ids);
    }

    public function clearMessages() {
        $this->messagesRepository->deleteAll();
    }

    public function getSettingsMap() {
        $map = $this->configSettings;
        foreach ($map as $k => $v) {
            $map[$k] = (string) $v;
        }
        foreach ($this->settingsRepository->findAll() as $setting) {
            if (array_key_exists($setting->getKey() . "_overridable", $this->configSettings) && $this->configSettings[$setting->getKey() . "_overridable"] === "0") {
                continue;
            }
            $map[$setting->getKey()] = $setting->getValue();
        }
        return $map;
    }

    public function getSettingValue($key) {
        $map = $this->getSettingsMap();
        if (array_key_exists($key, $map)) {
            return $map[$key];
        }
        return null;
    }

    public function isApiEnabled() {
        $enabled = $this->getSettingValue("api_enabled");
        return $enabled == "1";
    }

    public function getLastMessageFetchTime() {
        $time = $this->getSettingValue("last_message_fetch_time");
        if ($time !== null)
            return (int) $time;
        return null;
    }

    public function getSessionLimit() {
        $limit = $this->getSettingValue("session_limit");
        return (int) $limit;
    }

    public function setSettings($map) {
        foreach ($map as $k => $v) {
            if (strpos($k, "_overridable") !== false)
                continue;
            if (array_key_exists($k . "_overridable", $this->configSettings) && $this->configSettings[$k . "_overridable"] === "0")
                continue;
            $setting = $this->settingsRepository->findKey($k);
            if ($setting) {
                $setting->setValue($v);
                $setting->setUpdated();
                $this->settingsRepository->save($setting);
            } else {
                $setting = new AdministrationSetting();
                $setting->setKey($k);
                $setting->setValue($v);
                $this->settingsRepository->save($setting);
            }
        }
    }

}
