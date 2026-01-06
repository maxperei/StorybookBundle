<?php

namespace Storybook;

use Storybook\Exception\RenderException;
use Storybook\Exception\UnauthorizedStoryException;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Markup;
use Twig\Sandbox\SecurityError;

final class StoryRenderer
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    public function render(Story $story): string
    {
        $storyTemplateName = \sprintf('story_%s.html.twig', $story->getId());

        $loader = new ChainLoader([
            new ArrayLoader([
                $story->getTemplateName() => $story->getTemplate(),
                $storyTemplateName => \sprintf("{%% sandbox %%} {%%- include '%s' -%%} {%% endsandbox %%}", $story->getTemplateName()),
            ]),
            $originalLoader = $this->twig->getLoader(),
        ]);

        $this->twig->setLoader($loader);

        try {
            return $this->twig->render($storyTemplateName, $this->renderMarkup($story->getArgs()->toArray()));
        } catch (SecurityError $th) {
            // SecurityError can actually be raised
            throw new UnauthorizedStoryException('Story contains unauthorized content', $th);
        } catch (Error $th) {
            throw new RenderException(\sprintf('Story render failed: %s', $th->getMessage()), $th);
        } finally {
            // Restore original loader
            $this->twig->setLoader($originalLoader);
        }
    }

    public function renderMarkup(array $args): array
    {
        if ([] === $subTemplates = array_filter($args, static fn($value) => is_array($value))) {
            return $args;
        }

        foreach ($subTemplates as $key => $sub) {
            $raw = array_key_exists('source', $sub) ? $sub['source'] : $args[$key];
            $args[$key] = new Markup($this->twig->createTemplate($raw)->render(), 'UTF-8');
        }

        return $args;
    }
}
