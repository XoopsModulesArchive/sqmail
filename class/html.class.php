<?php
/**
 * html.class.php
 *
 * Copyright (c) 2003 The SquirrelMail Project Team
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * This contains functions needed to generate html output.
 *
 * $Id: html.class.php,v 1.4 2002/12/31 12:49:29 kink Exp $
 */
class html
{
    public $tag;

    public $text;

    public $style;

    public $class;

    public $id;

    public $html_el = [];

    public $javascript;

    public $xtr_prop;

    public function __construct(
        $tag = '',
        $text = '',
        $style = '',
        $class = '',
        $id = '',
        $xtr_prop = '',
        $javascript = ''
    ) {
        $this->tag = $tag;

        $this->text = $text;

        $this->style = $style;

        $this->class = $class;

        $this->id = $id;

        $this->xtr_prop = $xtr_prop;

        $this->javascript = $javascript;
    }

    public function htmlAdd($el, $last = true)
    {
        if ($last) {
            $this->html_el[] = $el;
        } else {
            $new_html_el = [];

            $new_html_el[] = $el;

            foreach ($this->html_el as $html_el) {
                $new_html_el[] = $html_el;
            }

            $this->html_el = $new_html_el;
        }
    }

    public function AddChild(
        $tag = '',
        $text = '',
        $style = '',
        $class = '',
        $id = '',
        $xtr_prop = '',
        $javascript = ''
    ) {
        $el = new self($tag, $text, $style, $class, $id, $xtr_prop, $javascript);

        $this->htmlAdd($el);
    }

    public function FindId($id)
    {
        $cnt = count($this->html_el);

        $el = false;

        if ($cnt) {
            for ($i = 0; $i < $cnt; $i++) {
                if ($this->html_el[$i]->id == $id) {
                    $ret = $this->html_el[$i];

                    return $ret;
                } elseif (count($this->html_el[$i]->html_el)) {
                    $el = $this->html_el[$i]->FindId($id);
                }

                if ($el) {
                    return $el;
                }
            }
        }

        return $el;
    }

    public function InsToId($el, $id, $last = true)
    {
        $html_el = $this->FindId($id);

        if ($html_el) {
            $html_el->htmlAdd($el, $last);
        }
    }

    public function scriptAdd($script)
    {
        $s = "\n" . '<!--' . "\n" . $script . "\n" . '// -->' . "\n";

        $el = new self(
            'script',
            $s,
            '',
            '',
            '',
            [
            'language' => 'JavaScript',
            'type' => 'text/javascript',
        ]
        );

        $this->htmlAdd($el);
    }

    public function echoHtml($usecss = false, $indent = 'x')
    {
        if ('x' == $indent) {
            $indent = '';

            $indentmore = '';
        } else {
            $indentmore = $indent . '  ';
        }

        $tag = $this->tag;

        $text = $this->text;

        $class = $this->class;

        $id = $this->id;

        $style = $this->style;

        $javascript = $this->javascript;

        $xtr_prop = $this->xtr_prop;

        if ($xtr_prop) {
            $prop = '';

            foreach ($xtr_prop as $k => $v) {
                if (is_string($k)) {
                    $prop .= ' ' . $k . '="' . $v . '"';
                } else {
                    $prop .= ' ' . $v;
                }
            }
        }

        if ($javascript) {
            $js = '';

            foreach ($javascript as $k => $v) { /* here we put the onclick, onmouseover etc entries */
                $js .= ' ' . $k . '="' . $v . '";';
            }
        }

        if ($tag) {
            echo $indent . '<' . $tag;
        } else {
            echo $indent;
        }

        if ($class) {
            echo ' class="' . $class . '"';
        }

        if ($id) {
            echo ' id="' . $id . '"';
        }

        if ($xtr_prop) {
            echo ' ' . $prop;
        }

        if ($style && !$usecss && !is_array($style)) {
            /* last premisse is to prevent 'style="Array"' in the output */

            echo ' style="' . $style . '"';
        }

        if ($javascript) {
            echo ' ' . $js;
        }

        if ($tag) {
            echo '>';
        }

        $openstyles = '';

        $closestyles = '';

        if ($style && !$usecss) {
            foreach ($style as $k => $v) {
                $openstyles .= '<' . $k . '>';
            }

            foreach ($style as $k => $v) {
                /* if value of key value = true close the tag */

                if ($v) {
                    $closestyles .= '</' . $k . '>';
                }
            }
        }

        echo $openstyles;

        if ($text) {
            echo $text;
        }

        $cnt = count($this->html_el);

        if ($cnt) {
            echo "\n";

            for ($i = 0; $i < $cnt; $i++) {
                $el = $this->html_el[$i];

                $el->echoHtml($usecss, $indentmore);
            }

            echo $indent;
        }

        echo $closestyles;

        if ($tag) {
            echo '</' . $tag . '>' . "\n";
        } else {
            echo "\n";
        }
    }
}
