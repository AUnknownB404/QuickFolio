<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectSkill\AttachProjectSkillRequest;
use App\Models\Portfolio;
use App\Models\Project;
use App\Models\Skill;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProjectSkillController extends Controller
{
    public function index(Portfolio $portfolio, Project $project): Response
    {
        $this->authorize('view', $portfolio);

        $attachedSkills = $project->skills()
            ->orderBy('name')
            ->get(['skills.id', 'skills.name']);

        $availableSkills = $portfolio->skills()
            ->whereDoesntHave('projects', fn ($query) => $query->whereKey($project->id))
            ->orderBy('name')
            ->get(['skills.id', 'skills.name']);

        return Inertia::render('ProjectSkills/Index', [
            'portfolio' => [
                'id' => $portfolio->id,
                'title' => $portfolio->title,
            ],
            'project' => [
                'id' => $project->id,
                'title' => $project->title,
            ],
            'attachedSkills' => $attachedSkills,
            'availableSkills' => $availableSkills,
        ]);
    }

    public function store(
        AttachProjectSkillRequest $request,
        Portfolio $portfolio,
        Project $project,
    ): RedirectResponse {
        $project->skills()->attach($request->validated('skill_id'));

        return redirect()->route('portfolios.projects.skills.index', [$portfolio, $project]);
    }

    public function destroy(Portfolio $portfolio, Project $project, Skill $skill): RedirectResponse
    {
        $this->authorize('update', $portfolio);

        $project->skills()->detach($skill->id);

        return redirect()->route('portfolios.projects.skills.index', [$portfolio, $project]);
    }
}
