<?php
/**
 * Created by PhpStorm.
 * User: steve
 * Date: 19/04/17
 * Time: 17:16
 */

namespace Ged\ApiProblem;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Interface ApiExceptionInterface
 *
 * @package Ged\Exception
 */
interface ApiProblemExceptionInterface extends \Throwable
{

    /**
     * @param TranslatorInterface $translator
     *
     * @return string
     */
    public function getDetail(TranslatorInterface $translator): string;

    /**
     * @return string
     */
    public function getInstance();

    /**
     * @param TranslatorInterface $translator
     *
     * @return string
     */
    public function getTitle(TranslatorInterface $translator);

    /**
     * @return string
     */
    public function getType();

    /**
     * @return int
     */
    public function getStatus();
}
