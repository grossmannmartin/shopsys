<?php

namespace Shopsys\FrameworkBundle\Component\Translation;

use JMS\TranslationBundle\Model\MessageCatalogue;
use JMS\TranslationBundle\Translation\Extractor\File\TwigFileExtractor as OriginalTwigFileExtractor;
use JMS\TranslationBundle\Translation\Extractor\FileVisitorInterface;
use ReflectionObject;
use SplFileInfo;
use Twig\Node\Node;

class TwigFileExtractor implements FileVisitorInterface
{
    /**
     * @var \JMS\TranslationBundle\Translation\Extractor\File\TwigFileExtractor
     */
    protected $originalTwigFileExtractor;

    /**
     * @param \JMS\TranslationBundle\Translation\Extractor\File\TwigFileExtractor $originalTwigFileExtractor
     */
    public function __construct(OriginalTwigFileExtractor $originalTwigFileExtractor)
    {
        $this->originalTwigFileExtractor = $originalTwigFileExtractor;

        $this->injectCustomVisitor();
    }

    /**
     * We want to extract messages from custom Twig translation filter "transHtml"
     * but original \JMS\TranslationBundle\Translation\Extractor\File\TwigFileExtractor is not open for that type of extension
     * so we need to inject our \Shopsys\FrameworkBundle\Component\Translation\CustomTransFiltersVisitor using ReflectionObject
     */
    protected function injectCustomVisitor()
    {
        $reflectionObject = new ReflectionObject($this->originalTwigFileExtractor);
        $traverserReflectionProperty = $reflectionObject->getProperty('traverser');
        $traverserReflectionProperty->setAccessible(true);
        /** @var \Twig\NodeTraverser $traverser */
        $traverser = $traverserReflectionProperty->getValue($this->originalTwigFileExtractor);
        $traverser->addVisitor(new CustomTransFiltersVisitor());
    }

    /**
     * {@inheritdoc}
     */
    public function visitFile(SplFileInfo $file, MessageCatalogue $catalogue)
    {
        $this->originalTwigFileExtractor->visitFile($file, $catalogue);
    }

    /**
     * {@inheritdoc}
     */
    public function visitPhpFile(SplFileInfo $file, MessageCatalogue $catalogue, array $ast)
    {
        $this->originalTwigFileExtractor->visitPhpFile($file, $catalogue, $ast);
    }

    /**
     * {@inheritdoc}
     */
    public function visitTwigFile(SplFileInfo $file, MessageCatalogue $catalogue, Node $ast)
    {
        $this->originalTwigFileExtractor->visitTwigFile($file, $catalogue, $ast);
    }
}
