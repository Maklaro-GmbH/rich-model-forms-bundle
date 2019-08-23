<?php

/*
 * This file is part of the RichModelFormsBundle package.
 *
 * (c) Christian Flothmann <christian.flothmann@sensiolabs.de>
 * (c) Christopher Hertel <christopher.hertel@sensiolabs.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace SensioLabs\RichModelForms\ExceptionHandling;

use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * @author Christian Flothmann <christian.flothmann@sensiolabs.de>
 */
class FormExceptionHandler
{
    private $exceptionHandlerRegistry;
    private $translator;
    private $translationDomain;

    public function __construct(ExceptionHandlerRegistry $exceptionHandlerRegistry, TranslatorInterface $translator = null, string $translationDomain = null)
    {
        $this->exceptionHandlerRegistry = $exceptionHandlerRegistry;
        $this->translator = $translator;
        $this->translationDomain = $translationDomain;
    }

    public function handleException(FormInterface $form, $data, \Throwable $e): void
    {
        $exceptionHandlers = [];

        if (null !== $form->getConfig()->getOption('handle_exception')) {
            foreach ($form->getConfig()->getOption('handle_exception') as $exceptionClass) {
                $exceptionHandlers[] = new GenericExceptionHandler($exceptionClass);
            }

            $exceptionHandlers[] = $this->exceptionHandlerRegistry->get('type_error');
        } else {
            foreach ($form->getConfig()->getOption('exception_handling_strategy') as $strategy) {
                $exceptionHandlers[] = $this->exceptionHandlerRegistry->get($strategy);
            }
        }

        if (1 === \count($exceptionHandlers)) {
            $exceptionHandler = reset($exceptionHandlers);
        } else {
            $exceptionHandler = new ChainExceptionHandler($exceptionHandlers);
        }

        if (null !== $error = $exceptionHandler->getError($form->getConfig(), $data, $e)) {
            if (null !== $this->translator) {
                $message = $this->translator->trans($error->getMessageTemplate(), $error->getParameters(), $this->translationDomain);
            } else {
                $message = strtr($error->getMessageTemplate(), $error->getParameters());
            }

            $form->addError(new FormError($message, $error->getMessageTemplate(), $error->getParameters(), null, $error->getCause()));
        } else {
            throw $e;
        }
    }
}
