<?php

declare(strict_types=1);

namespace Glavweb\AdminBundle\Maker\Manipulator;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * @author Marek Stipek <mario.dweller@seznam.cz>
 * @author Simon Cosandey <simon.cosandey@simseo.ch>
 */
class AdminServicesManipulator
{
    private string $file;

    public function __construct(string $file)
    {
        $this->file = (string) $file;
    }

    public function addResource(
        string $serviceId,
        string $modelClass,
        string $adminClass,
        string $controllerName,
        string $managerType,
        string $group,
        ?string $managerClass,
    ): void {
        $code = "services:\n";

        if (is_file($this->file)) {
            $content = file_get_contents($this->file);
            if (false === $content) {
                throw new \LogicException('Не удалось прочитать файл');
            }
            $code = rtrim($content);
            $data = (array) Yaml::parse($code);

            if ('' !== $code) {
                $code .= "\n";
            }

            if (array_key_exists('services', $data)) {
                if (array_key_exists($serviceId, (array) $data['services'])) {
                    throw new RuntimeException(sprintf('The service "%s" is already defined in the file "%s".', $serviceId, realpath($this->file)));
                }

                if (null !== $data['services']) {
                    $code .= "\n";
                }
            } else {
                $code .= '' === $code ? '' : "\nservices:\n";
            }
        }

        $normalizedServiceId = str_replace('.', '_', $serviceId);
        $label = 'dashboard.label_'.$normalizedServiceId;
        $translationDomain = $normalizedServiceId;

        $code .= "    $serviceId:\n";
        $code .= "        class: $adminClass\n";
        $code .= "        tags:\n";
        $code .= "            - {name: sonata.admin, model_class: $modelClass, controller: $controllerName, manager_type: $managerType, group: $group, label: $label, label_translator_strategy: \"sonata.admin.label.strategy.underscore\"}\n";
        $code .= "        calls:\n";
        $code .= "            - [ setTranslationDomain, [\"$translationDomain\"]]\n";

        if ($managerClass) {
            $managerClassNormalized = str_replace('\\', '\\\\', $managerClass);
            $code .= "            - [ setManager, [\"@$managerClassNormalized\"]]\n";
        }

        @mkdir(dirname($this->file), 0777, true);

        if (false === @file_put_contents($this->file, $code)) {
            throw new RuntimeException(sprintf('Unable to append service "%s" to the file "%s". You will have to do it manually.', $serviceId, $this->file));
        }
    }
}
