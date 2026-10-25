<?php

namespace App\Services;

use App\Models\FieldDefinition;
use App\Models\Portfolio;
use App\Models\PortfolioFieldValue;
use App\Models\PortfolioSection;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PortfolioService
{
    public function createForUser(User $user, Template $template, string $title, array $initialContent = []): Portfolio
    {
        return DB::transaction(function () use ($user, $template, $title, $initialContent) {
            $template->loadMissing('sections');
            $demoFieldKeys = collect($initialContent)
                ->flatMap(fn (array $sectionContent) => array_keys($sectionContent))
                ->unique()
                ->all();
            $fieldDefinitions = FieldDefinition::whereIn('field_key', $demoFieldKeys)->get()->keyBy('field_key');

            $slug = Str::slug($title);
            $count = Portfolio::where('slug', 'like', "{$slug}%")->count();
            if ($count > 0) {
                $slug = "{$slug}-" . ($count + 1);
            }

            $portfolio = Portfolio::create([
                'user_id' => $user->id,
                'template_id' => $template->id,
                'title' => $title,
                'slug' => $slug,
                'status' => 'draft',
                'design_settings' => [],
            ]);

            // Initialize portfolio sections from template sections
            foreach ($template->sections as $templateSection) {
                $portfolioSection = PortfolioSection::create([
                    'portfolio_id' => $portfolio->id,
                    'section_key' => $templateSection->section_key,
                    'is_enabled' => $templateSection->is_enabled,
                    'display_order' => $templateSection->display_order,
                ]);

                foreach ($initialContent[$templateSection->section_key] ?? [] as $fieldKey => $value) {
                    $fieldDefinition = $fieldDefinitions->get($fieldKey);

                    if ($fieldDefinition && $value !== null) {
                        PortfolioFieldValue::create([
                            'portfolio_section_id' => $portfolioSection->id,
                            'field_definition_id' => $fieldDefinition->id,
                            'value' => $value,
                        ]);
                    }
                }
            }

            return $portfolio;
        });
    }

    public function switchTemplate(Portfolio $portfolio, Template $newTemplate): Portfolio
    {
        return DB::transaction(function () use ($portfolio, $newTemplate) {
            $portfolio->template_id = $newTemplate->id;
            $portfolio->save();

            // Reconcile sections: ensure all new template sections exist in portfolio sections
            $existingSectionKeys = $portfolio->sections()->pluck('section_key')->toArray();

            foreach ($newTemplate->sections as $newSection) {
                if (!in_array($newSection->section_key, $existingSectionKeys)) {
                    PortfolioSection::create([
                        'portfolio_id' => $portfolio->id,
                        'section_key' => $newSection->section_key,
                        'is_enabled' => $newSection->is_enabled,
                        'display_order' => $newSection->display_order,
                    ]);
                }
            }

            return $portfolio->load(['sections.fieldValues.fieldDefinition', 'template.sections.fields.fieldDefinition']);
        });
    }
}
