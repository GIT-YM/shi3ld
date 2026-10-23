<?php

declare(strict_types=1);

namespace Shi3ld\Blog\Model\Editorial;

class Starters
{
    public function all(): array
    {
        return [
            'how-to' => ['title' => 'Step-by-step guide', 'html' => '<section class="blog-copy"><h2>What this guide helps you do</h2><p>Describe the reader’s goal and who this guide is for.</p><h2>Before you begin</h2><ul><li>List the equipment or preparation required.</li></ul><h2>Step 1: Get ready</h2><p>Explain the first step.</p><h2>Step 2: Complete the task</h2><p>Explain what to do next.</p><h2>Check the result</h2><p>Explain how to confirm the task is complete.</p></section>'],
            'buying-guide' => ['title' => 'Buying guide', 'html' => '<section class="blog-copy"><h2>Choosing the right option</h2><p>Explain the problem this guide addresses.</p><h2>What matters most</h2><ul><li>Describe an important selection criterion.</li></ul><h2>Options to consider</h2><p>Explain the choices and their trade-offs. Assign relevant products in the Blog panel.</p><h2>Before you decide</h2><p>Summarise the practical checks a reader should make.</p></section>'],
            'comparison' => ['title' => 'Product or service comparison', 'html' => '<section class="blog-copy"><h2>Comparing the options</h2><p>Describe the reader’s needs and the options being compared.</p><div class="blog-table"><table><thead><tr><th scope="col">Consideration</th><th scope="col">Option A</th><th scope="col">Option B</th></tr></thead><tbody><tr><th scope="row">Best suited to</th><td>Add verified information.</td><td>Add verified information.</td></tr><tr><th scope="row">Things to consider</th><td>Describe the trade-offs.</td><td>Describe the trade-offs.</td></tr></tbody></table></div><h2>How to choose</h2><p>Explain which needs each option addresses.</p></section>'],
            'case-study' => ['title' => 'Case study', 'html' => '<section class="blog-copy"><h2>The starting point</h2><p>Describe the approved client situation without identifying anyone unnecessarily.</p><h2>The objective</h2><p>Describe the agreed goal.</p><h2>The approach</h2><p>Explain the work carried out.</p><h2>The result</h2><p>Add verified results and approved photographs or quotations.</p><h2>What others can learn</h2><p>Explain the useful takeaway.</p></section>'],
        ];
    }
}
