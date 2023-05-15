<?php

namespace App\Domain\Repositories;

use App\Domain\Models\JobVacancy;
use Illuminate\Database\Eloquent\Builder;


interface JobVacancyRepositoryInterface
{
    public function getJobVacancyList(): array;

    public function getJobVacancyById(int $id): JobVacancy|Builder|null;

    public function createJobVacancy(array $data): JobVacancy|Builder;

    public function updateJobVacancy(int $id, array $data): JobVacancy|Builder;

    public function deleteJobVacancy($id): JobVacancy|Builder;
}
