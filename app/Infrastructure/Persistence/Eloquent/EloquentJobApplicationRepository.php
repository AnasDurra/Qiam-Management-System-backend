<?php


namespace App\Infrastructure\Persistence\Eloquent;


use App\Domain\Models\Address;
use App\Domain\Models\ComputerSkill;
use App\Domain\Models\EmpData;
use App\Domain\Models\JobApplication;
use App\Domain\Models\Language;
use App\Domain\Models\Passport;
use App\Domain\Models\PersonalCard;
use App\Domain\Models\Skill;
use App\Domain\Repositories\JobApplicationRepositoryInterface;
use App\Utils\StorageUtilities;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EloquentJobApplicationRepository implements JobApplicationRepositoryInterface
{

    // implement get job applications list with pagination
    public function getJobApplicationsList(): LengthAwarePaginator
    {
        return JobApplication::query()->paginate(10);
    }

    /**
     * @throws Exception
     */
    public function getJobApplicationById(int $id): Builder|array|Collection|Model
    {
        return JobApplication::query()->findOrFail($id);
    }

    /**
     * @throws Exception
     */
    public function createJobApplication(array $data): Builder|Model
    {

        try {

            // start transaction
            DB::beginTransaction();

            // store employee personal photo in local storage
            $data['personal_data']['personal_photo'] =
                StorageUtilities::storePersonalPhoto($data['personal_data']['personal_photo']);


            // create personal card data for this employee data
            $personalCard = new PersonalCard([
                "card_number" => $data['personal_card']['card_number'],
                "place_of_issue" => $data['personal_card']['card_place_of_issue'],
                "date_of_issue" => $data['personal_card']['card_date_of_issue'],
            ]);

            // save personal card data
            $personalCard->save();

            // create passport data for this employee data (if exists)
            if (isset($data['passport'])) {
                $passport = new Passport([
                    "passport_number" => $data['passport']['passport_number'],
                    "place_of_issue" => $data['passport']['passport_place_of_issue'],
                    "date_of_issue" => $data['passport']['passport_date_of_issue'],
                ]);

                // save passport data
                $passport->save();
            }


            // address data
            $address = new Address([
                "state" => $data['address']['state'],
                "city" => $data['address']['city'],
                "street" => $data['address']['street'],
                "postal_code" => optional($data['address'])['postal_code'],
                "email" => optional($data['address'])['email'],
                "mobile_no" => optional($data['address'])['mobile_no'],
                "home_phone_no" => optional($data['address'])['home_phone_no'],
                "work_phone_no" => optional($data['address'])['work_phone_no'],
            ]);

            // save address data
            $address->save();

            // create job application data
            $employeeData = EmpData::query()->create([

                // personal data fields
                "first_name" => $data['personal_data']['first_name'],
                "last_name" => $data['personal_data']['last_name'],
                "father_name" => $data['personal_data']['father_name'],
                "grand_father_name" => $data['personal_data']['grand_father_name'],
                "personal_photo" => $data['personal_data']['personal_photo'],
                "birth_date" => $data['personal_data']['birth_date'],
                "birth_place" => $data['personal_data']['birth_place'],
                "marital_status" => $data['personal_data']['marital_status'],

                // job data
                "start_working_date" => $data['job_data']['start_working_date'],
                "is_employed" => $data['job_data']['is_employed'],

                // passport data
                "passport_id" => isset($passport)
                    ? $passport->getAttribute('passport_id')
                    : null,

                // personal card data
                "card_id" => $personalCard->getAttribute('personal_card_id'),

                // address data
                "address_id" => $address->getAttribute('address_id'),
            ]);

            // check if passport is passed, assign the object ot the empData


            // create job application for this employee data
            $employeeData->jobApplication()->create([
                "job_vacancy_id" => $data['job_application']['job_vacancy_id'],
                "app_status_id" => 1, // needs to be processed
                "section_man_notes" => optional($data['job_application'])['section_man_notes'],
                "vice_man_rec" => optional($data['job_application'])['vice_man_rec'],
            ]);


            // driving licence data (if exists)
            if (isset($data['driving_licence'])) {
                $employeeData->drivingLicence()->create([
                    "category" => optional($data['driving_licence'])['category'],
                    "date_of_issue" => $data['driving_licence']['date_of_issue'],
                    "place_of_issue" => $data['driving_licence']['place_of_issue'],
                    "number" => $data['driving_licence']['number'],
                    "expiry_date" => $data['driving_licence']['expiry_date'],
                    "blood_group" => $data['driving_licence']['blood_group'],
                ]);
            }

            // dependents data (if exists)
            if (isset($data['dependants'])) {
                foreach ($data['dependants'] as $dependant) {
                    $employeeData->dependents()->create([
                        "name" => $dependant['name'],
                        "age" => $dependant['age'],
                        "relation" => $dependant['relationship'],
                        "address" => $dependant['address'],
                    ]);
                }
            }

            // previous employment records data (if exists)
            if (isset($data['previous_employment_record'])) {
                foreach ($data['previous_employment_record'] as $record) {
                    $employeeData->previousEmploymentRecords()->create([
                        "employer_name" => $record['employer_name'],
                        "address" => $record['address'],
                        "telephone" => $record['telephone'],
                        "job_title" => $record['job_title'],
                        "job_description" => $record['job_description'],
                        "start_date" => $record['start_date'],
                        "end_date" => $record['end_date'],
                        "salary" => $record['salary'],
                        "allowance" => $record['allowance'],
                        "quit_reason" => optional($data)['quit_reason'],
                    ]);
                }
            }

            // convictions (if exists)
            if (isset($data['convictions'])) {
                foreach ($data['convictions'] as $conviction) {
                    $employeeData->convictions()->create([
                        "description" => $conviction['description'],
                    ]);
                }
            }

            // education records (if exists)
            if (isset($data['education'])) {
                foreach ($data['education'] as $record) {
                    // attach education level to this employee data
                    $employeeData->educationLevels()->attach($record['education_level_id'], [
                        "univ_name" => $record['univ_name'],
                        "city" => $record['city'],
                        "start_date" => $record['start_date'],
                        "end_date" => $record['end_date'],
                        "specialize" => optional($record)['specialize'],
                        "grade" => optional($record)['grade'],
                    ]);
                }
            }

            // training courses (if exists)
            if (isset($data['training_courses'])) {
                foreach ($data['training_courses'] as $course) {
                    $employeeData->trainingCourses()->create([
                        "name" => $course['course_name'],
                        "institute_name" => $course['institute_name'],
                        "city" => $course['city'],
                        "start_date" => $course['start_date'],
                        "end_date" => $course['end_date'],
                        "specialize" => $course['specialize'],
                    ]);
                }
            }

            // skills (if exists)
            if (isset($data['skills'])) {
                foreach ($data['skills'] as $skill) {
                    // create skill if not exists
                    $skillModel = Skill::query()->firstOrCreate([
                        "name" => $skill['skill_name'],
                    ]);

                    // attach skill to this employee data
                    $employeeData->skills()->attach($skillModel->getAttribute("skill_id"));
                }
            }

            // languages (if exists)
            if (isset($data['languages'])) {
                foreach ($data['languages'] as $language) {
                    // create language if not exists
                    $languageModel = Language::query()->firstOrCreate([
                        "name" => $language['language_name'],
                    ]);

                    // attach language to this employee data
                    $employeeData->languages()->attach($languageModel->getAttribute("language_id"), [
                        "reading_level" => $language['reading'],
                        "writing_level" => $language['writing'],
                        "speaking_level" => $language['speaking'],
                    ]);
                }
            }

            // computer skills (if exists)
            if (isset($data['computer_skills'])) {
                foreach ($data['computer_skills'] as $skill) {
                    // create computer skill if not exists
                    $computerSkillModel = ComputerSkill::query()->firstOrCreate([
                        "name" => $skill['skill_name'],
                    ]);

                    // attach computer skill to this employee data
                    $employeeData->computerSkills()->attach($computerSkillModel->getAttribute("computer_skill_id"), [
                        "level" => $skill['level'],
                    ]);
                }
            }

            // relatives (from center employees) (if exists)
            if (isset($data['relatives'])) {
                foreach ($data['relatives'] as $relative) {
                    // TODO: Check that if it's working or not
                    // attach relative to this employee data
                    $employeeData->relatives()->attach($relative["emp_id"]);
                }
            }

            // references (if exists)
            if (isset($data['references'])) {
                foreach ($data['references'] as $reference) {
                    $employeeData->references()->create([
                        "name" => $reference['name'],
                        "job" => $reference['job'],
                        "company" => $reference['company'],
                        "address" => $reference['address'],
                        "telephone" => $reference['telephone'],
                    ]);
                }
            }

            // certificates (if exists)
            // store certificates in local storage
            if (isset($data['certificates'])) {
                foreach ($data['certificates'] as $certificate) {
                    $employeeData->certificates()->create([
                        "name" => $certificate['certificate_name'],
                        "file_url" => StorageUtilities::storeCertificate($certificate['file']),
                    ]);
                }
            }

            // save employee data
            $employeeData->save();

            
            // create a job application with this employee data
            $jobApplication = $employeeData->jobApplication()->create([
                "job_vacancy_id" => $data['job_application']["job_vacancy_id"],
                "section_man_notes" => optional($data['job_application'])["section_man_notes"],
                "vice_man_rec" => optional($data['job_application'])["vice_man_rec"],
                "app_status_id" => 1, // set app status id by default to 1 (pending)
            ]);

            DB::commit();

            return $jobApplication;
        } catch (Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }


    /**
     * @throws Exception
     */
    public function updateJobApplication(int $id, array $data): Builder|Model
    {
        try {

            // start transaction
            DB::beginTransaction();

            // first, find the job application
            $jobApplication = JobApplication::query()->findOrFail($id);

            /// Here we will update the job application data
            /// and all related data to it

            // job application data
            if (optional($data)['job_application']) {

                $updated = [];
                $jobApplicationData = $data['job_application'];

                // check job vacancy id
                if (
                    optional($jobApplicationData)['job_vacancy_id'] &&
                    optional($jobApplicationData)['job_vacancy_id'] != $jobApplication->getAttribute("job_vacancy_id")
                ) {
                    $updated['job_vacancy_id'] = $jobApplicationData['job_vacancy_id'];
                }

                // check section manager notes
                if (
                    optional($jobApplicationData)['section_man_notes'] &&
                    optional($jobApplicationData)['section_man_notes'] != $jobApplication->getAttribute("section_man_notes")
                ) {
                    $updated['section_man_notes'] = $jobApplicationData['section_man_notes'];
                }

                // check vice manager recommendation
                if (
                    optional($jobApplicationData)['vice_man_rec'] &&
                    optional($jobApplicationData)['vice_man_rec'] != $jobApplication->getAttribute("vice_man_rec")
                ) {
                    $updated['vice_man_rec'] = $jobApplicationData['vice_man_rec'];
                }

                // check if there is any update
                if (count($updated) > 0) {
                    $jobApplication->update($updated);
                }
            }

            //  Employee data (personal data)
            if (optional($data)['personal_data']) {

                $updated = [];
                $personalData = $data['personal_data'];

                // check that employee data exists
                if (isset($jobApplication->empData)) {

                    // check first name
                    if (
                        optional($personalData)['first_name'] &&
                        optional($personalData)['first_name'] != $jobApplication->empData->getAttribute("first_name")
                    ) {
                        $updated['first_name'] = $personalData['first_name'];
                    }

                    // check last name
                    if (
                        optional($personalData)['last_name'] &&
                        optional($personalData)['last_name'] != $jobApplication->empData->getAttribute("last_name")
                    ) {
                        $updated['last_name'] = $personalData['last_name'];
                    }

                    // check personal photo
                    if (
                        optional($personalData)['personal_photo'] &&
                        optional($personalData)['personal_photo'] != $jobApplication->empData->getAttribute("personal_photo")
                    ) {

                        // in this case, the user has sent a file instead of a the file url.
                        // so we will delete the old file and store the new one.
                        // and update the file url in the database
                        StorageUtilities::deletePersonalPhoto($jobApplication->empData->getAttribute("personal_photo"));
                        $updated['personal_photo'] = StorageUtilities::storePersonalPhoto($personalData['personal_photo']);
                    }

                    // check father name
                    if (
                        optional($personalData)['father_name'] &&
                        optional($personalData)['father_name'] != $jobApplication->empData->getAttribute("father_name")
                    ) {
                        $updated['father_name'] = $personalData['father_name'];
                    }

                    // check grand father name
                    if (
                        optional($personalData)['grand-father_name'] &&
                        optional($personalData)['grand-father_name'] != $jobApplication->empData->getAttribute("grand_father_name")
                    ) {
                        $updated['grand_father_name'] = $personalData['grand-father_name'];
                    }

                    // check birth date
                    if (
                        optional($personalData)['birth_date'] &&
                        optional($personalData)['birth_date'] != $jobApplication->empData->getAttribute("birth_date")
                    ) {
                        $updated['birth_date'] = $personalData['birth_date'];
                    }

                    // check birth place
                    if (
                        optional($personalData)['birth_place'] &&
                        optional($personalData)['birth_place'] != $jobApplication->empData->getAttribute("birth_place")
                    ) {
                        $updated['birth_place'] = $personalData['birth_place'];
                    }

                    // check marital status
                    if (
                        optional($personalData)['marital_status'] &&
                        optional($personalData)['marital_status'] != $jobApplication->empData->getAttribute("marital_status")
                    ) {
                        $updated['marital_status'] = $personalData['marital_status'];
                    }

                    // check if there is any update
                    if (count($updated) > 0) {
                        $jobApplication->empData->update($updated);
                    }
                }
            }

            // Employee data (job data)
            if (optional($data)['job_data']) {

                $updated = [];
                $jobData = $data['job_data'];

                // check that employee data exists
                if (isset($jobApplication->empData)) {

                    // check start working date
                    if (
                        optional($jobData)['start_working_date'] &&
                        optional($jobData)['start_working_date'] != $jobApplication->empData->getAttribute("start_working_date")
                    ) {
                        $updated['start_working_date'] = $jobData['start_working_date'];
                    }

                    // check is employed
                    if (
                        !is_null(optional($jobData)['is_employed']) &&
                        optional($jobData)['is_employed'] != $jobApplication->empData->getAttribute("is_employed")
                    ) {
                        $updated['is_employed'] = $jobData['is_employed'];
                    }

                    // check if there is any update
                    if (count($updated) > 0) {
                        $jobApplication->empData->update($updated);
                    }
                }
            }

            // Employee data (personal card data)
            if (optional($data)['personal_card']) {

                $updated = [];
                $personalCardData = $data['personal_card'];

                // check that employee data exists
                if (isset($jobApplication->empData->personalCard)) {

                    // check card number
                    if (
                        optional($personalCardData)['card_number'] &&
                        $personalCardData['card_number'] != $jobApplication->empData->personalCard->getAttribute("card_number")
                    ) {
                        $updated['card_number'] = $personalCardData['card_number'];
                    }

                    // check card place of issue
                    if (
                        optional($personalCardData)['card_place_of_issue'] &&
                        optional($personalCardData)['card_place_of_issue'] != $jobApplication->empData->personalCard->getAttribute("place_of_issue")
                    ) {
                        $updated['place_of_issue'] = $personalCardData['card_place_of_issue'];
                    }

                    // check card date of issue
                    if (
                        optional($personalCardData)['card_date_of_issue'] &&
                        optional($personalCardData)['card_date_of_issue'] != $jobApplication->empData->personalCard->getAttribute("date_of_issue")
                    ) {
                        $updated['date_of_issue'] = $personalCardData['card_date_of_issue'];
                    }

                    // check if there is any update
                    if (count($updated) > 0) {
                        $jobApplication->empData->personalCard->update($updated);
                    }
                }
            }

            // Employee data (address data)
            if (optional($data)['address']) {

                $updated = [];
                $addressData = $data['address'];

                // check that employee data exists
                if (isset($jobApplication->empData)) {

                    // check state
                    if (
                        optional($addressData)['state'] &&
                        optional($addressData)['state'] != $jobApplication->empData->address->getAttribute("state")
                    ) {
                        $updated['state'] = $addressData['state'];
                    }

                    // check city
                    if (
                        optional($addressData)['city'] &&
                        optional($addressData)['city'] != $jobApplication->empData->address->getAttribute("city")
                    ) {
                        $updated['city'] = $addressData['city'];
                    }

                    // check street
                    if (
                        optional($addressData)['street'] &&
                        optional($addressData)['street'] != $jobApplication->empData->address->getAttribute("street")
                    ) {
                        $updated['street'] = $addressData['street'];
                    }

                    // check postal code
                    if (
                        optional($addressData)['postal_code'] &&
                        optional($addressData)['postal_code'] != $jobApplication->empData->address->getAttribute("postal_code")
                    ) {
                        $updated['postal_code'] = $addressData['postal_code'];
                    }

                    // check email
                    if (
                        optional($addressData)['email'] &&
                        optional($addressData)['email'] != $jobApplication->empData->address->getAttribute("email")
                    ) {
                        $updated['email'] = $addressData['email'];
                    }

                    // check mobile no
                    if (
                        optional($addressData)['mobile_no'] &&
                        optional($addressData)['mobile_no'] != $jobApplication->empData->address->getAttribute("mobile_no")
                    ) {
                        $updated['mobile_no'] = $addressData['mobile_no'];
                    }

                    // check home phone no
                    if (
                        optional($addressData)['home_phone_no'] &&
                        optional($addressData)['home_phone_no'] != $jobApplication->empData->address->getAttribute("home_phone_no")
                    ) {
                        $updated['home_phone_no'] = $addressData['home_phone_no'];
                    }

                    // check work phone no
                    if (
                        optional($addressData)['work_phone_no'] &&
                        optional($addressData)['work_phone_no'] != $jobApplication->empData->address->getAttribute("work_phone_no")
                    ) {
                        $updated['work_phone_no'] = $addressData['work_phone_no'];
                    }

                    // check if there is any update
                    if (count($updated) > 0) {
                        $jobApplication->empData->address->update($updated);
                    }
                }
            }

            // Employee data (passport data)
            if (optional($data)['passport']) {

                $updated = [];
                $passportData = $data['passport'];

                /*
                 * First we will check if there is a passport object linked with employee data,
                 * if so, we will modify it, otherwise we will create a new one. with the passed data.
                 */
                if (!isset($jobApplication->empData->passport)) {
                    $jobApplication->empData->passport()->create([
                        "passport_number" => $passportData['passport_number'],
                        "place_of_issue" => $passportData['place_of_issue'],
                        "date_of_issue" => $passportData['date_of_issue'],
                    ]);
                } else {
                    // check passport number
                    if (
                        optional($passportData)['passport_number'] &&
                        optional($passportData)['passport_number'] != $jobApplication->empData->passport->getAttribute("passport_number")
                    ) {
                        $updated['passport_number'] = $passportData['passport_number'];
                    }

                    // check passport place of issue
                    if (
                        optional($passportData)['passport_place_of_issue'] &&
                        optional($passportData)['passport_place_of_issue'] != $jobApplication->empData->passport->getAttribute("place_of_issue")
                    ) {
                        $updated['place_of_issue'] = $passportData['passport_place_of_issue'];
                    }

                    // check passport date of issue
                    if (
                        optional($passportData)['passport_date_of_issue'] &&
                        optional($passportData)['passport_date_of_issue'] != $jobApplication->empData->passport->getAttribute("date_of_issue")
                    ) {
                        $updated['date_of_issue'] = $passportData['passport_date_of_issue'];
                    }

                    // check if there is any update
                    if (count($updated) > 0) {
                        $jobApplication->empData->passport->update($updated);
                    }
                }
            }

            // Employee data (driving licence data)
            if (optional($data)['driving_licence']) {

                $updated = [];
                $drivingLicenceData = $data['driving_licence'];

                /*
                 * First we will check if there is a driving licence object linked with employee data,
                 * if so, we will modify it, otherwise we will create a new one. with the passed data.
                 */
                if (!isset($jobApplication->empData->drivingLicence)) {

                    // Create a new driving licence and link it with emp data
                    $drivingLicenceObj = $jobApplication->empData->drivingLicence()->create([
                        "category" => $drivingLicenceData['category'],
                        "date_of_issue" => $drivingLicenceData['date_of_issue'],
                        "place_of_issue" => $drivingLicenceData['place_of_issue'],
                        "number" => $drivingLicenceData['number'],
                        "expiry_date" => $drivingLicenceData['expiry_date'],
                        "blood_group" => $drivingLicenceData['blood_group'],
                    ]);

                    // Set the foreign key value in the emp_data table
                    $jobApplication->empData->driving_licence_id = $drivingLicenceObj->driving_licence_id;
                    $jobApplication->empData->save();
                } else {
                    // check driving licence category
                    if (
                        optional($drivingLicenceData)['category'] &&
                        optional($drivingLicenceData)['category'] != $jobApplication->empData->drivingLicence->getAttribute("category")
                    ) {
                        $updated['category'] = $drivingLicenceData['category'];
                    }

                    // check driving licence number
                    if (
                        optional($drivingLicenceData)['number'] &&
                        optional($drivingLicenceData)['number'] != $jobApplication->empData->drivingLicence->getAttribute("number")
                    ) {
                        $updated['number'] = $drivingLicenceData['number'];
                    }

                    // check driving licence date of issue
                    if (
                        optional($drivingLicenceData)['date_of_issue'] &&
                        optional($drivingLicenceData)['date_of_issue'] != $jobApplication->empData->drivingLicence->getAttribute("date_of_issue")
                    ) {
                        $updated['date_of_issue'] = $drivingLicenceData['date_of_issue'];
                    }

                    // check driving licence expiry date
                    if (
                        optional($drivingLicenceData)['expiry_date'] &&
                        optional($drivingLicenceData)['expiry_date'] != $jobApplication->empData->drivingLicence->getAttribute("expiry_date")
                    ) {
                        $updated['expiry_date'] = $drivingLicenceData['expiry_date'];
                    }

                    // check driving licence place of issue
                    if (
                        optional($drivingLicenceData)['place_of_issue'] &&
                        optional($drivingLicenceData)['place_of_issue'] != $jobApplication->empData->drivingLicence->getAttribute("place_of_issue")
                    ) {
                        $updated['place_of_issue'] = $drivingLicenceData['place_of_issue'];
                    }

                    // check driving licence blood group
                    if (
                        optional($drivingLicenceData)['blood_group'] &&
                        optional($drivingLicenceData)['blood_group'] != $jobApplication->empData->drivingLicence->getAttribute("blood_group")
                    ) {
                        $updated['blood_group'] = $drivingLicenceData['blood_group'];
                    }

                    // check if there is any update
                    if (count($updated) > 0) {
                        $jobApplication->empData->drivingLicence->update($updated);
                    }
                }
            }

            // Employee data (dependants data)
            if (optional($data)['dependants']) {

                $dependantsData = $data['dependants'];

                /*
                 * First we will check if the length of the dependants array is greater than 0,
                 * if so, we will go through each dependant and check if it has an id,
                 * if so, we will update only the fields that are passed,
                 * otherwise we will create a new one. with the passed data.
                 */
                if (count($dependantsData) > 0) {
                    foreach ($dependantsData as $dependant) {
                        // update dependant
                        if (isset(optional($dependant)['dependent_id'])) {

                            // get dependant object
                            $dependantObj = $jobApplication->empData->dependents()->find($dependant['dependent_id']);

                            // check dependant name
                            if (
                                optional($dependant)['name'] &&
                                optional($dependant)['name'] != $dependantObj->getAttribute("name")
                            ) {
                                $dependantObj->update(['name' => $dependant['name']]);
                            }

                            // check dependant age
                            if (
                                optional($dependant)['age'] &&
                                optional($dependant)['age'] != $dependantObj->getAttribute("age")
                            ) {
                                $dependantObj->update(['age' => $dependant['age']]);
                            }

                            // check dependant relationship
                            if (
                                optional($dependant)['relationship'] &&
                                optional($dependant)['relationship'] != $dependantObj->getAttribute("relation")
                            ) {
                                $dependantObj->update(['relation' => $dependant['relationship']]);
                            }

                            // check dependant address
                            if (
                                optional($dependant)['address'] &&
                                optional($dependant)['address'] != $dependantObj->getAttribute("address")
                            ) {
                                $dependantObj->update(['address' => $dependant['address']]);
                            }
                        } else {
                            // create dependant
                            $jobApplication->empData->dependants()->create($dependant);
                        }
                    }
                }
            }

            // Employee data (previous employment records)
            if (optional($data)['previous_employment_record']) {

                $previousEmploymentRecordData = $data['previous_employment_record'];

                /*
                 * First we will check if the length of the previous employment record array is greater than 0,
                 * if so, we will go through each previous employment record and check if it has an id,
                 * if so, we will update only the fields that are passed,
                 * otherwise we will create a new one. with the passed data.
                 */
                if (count($previousEmploymentRecordData) > 0) {
                    foreach ($previousEmploymentRecordData as $previousEmploymentRecord) {
                        // update previous employment record
                        if (isset(optional($previousEmploymentRecord)['prev_emp_record_id'])) {

                            // get previous employment record object
                            $previousEmploymentRecordObj = $jobApplication->empData->previousEmploymentRecords()->find($previousEmploymentRecord['prev_emp_record_id']);

                            // check previous employment record employer name
                            if (
                                optional($previousEmploymentRecord)['employer_name'] &&
                                optional($previousEmploymentRecord)['employer_name'] != $previousEmploymentRecordObj->getAttribute("employer_name")
                            ) {
                                $previousEmploymentRecordObj->update(['employer_name' => $previousEmploymentRecord['employer_name']]);
                            }

                            // check previous employment record address
                            if (
                                optional($previousEmploymentRecord)['address'] &&
                                optional($previousEmploymentRecord)['address'] != $previousEmploymentRecordObj->getAttribute("address")
                            ) {
                                $previousEmploymentRecordObj->update(['address' => $previousEmploymentRecord['address']]);
                            }

                            // check previous employment record telephone
                            if (
                                optional($previousEmploymentRecord)['telephone'] &&
                                optional($previousEmploymentRecord)['telephone'] != $previousEmploymentRecordObj->getAttribute("telephone")
                            ) {
                                $previousEmploymentRecordObj->update(['telephone' => $previousEmploymentRecord['telephone']]);
                            }

                            // check previous employment record job title
                            if (
                                optional($previousEmploymentRecord)['job_title'] &&
                                optional($previousEmploymentRecord)['job_title'] != $previousEmploymentRecordObj->getAttribute("job_title")
                            ) {
                                $previousEmploymentRecordObj->update(['job_title' => $previousEmploymentRecord['job_title']]);
                            }

                            // check previous employment record job description
                            if (
                                optional($previousEmploymentRecord)['job_description'] &&
                                optional($previousEmploymentRecord)['job_description'] != $previousEmploymentRecordObj->getAttribute("job_description")
                            ) {
                                $previousEmploymentRecordObj->update(['job_description' => $previousEmploymentRecord['job_description']]);
                            }

                            // check previous employment record start date
                            if (
                                optional($previousEmploymentRecord)['start_date'] &&
                                optional($previousEmploymentRecord)['start_date'] != $previousEmploymentRecordObj->getAttribute("start_date")
                            ) {
                                $previousEmploymentRecordObj->update(['start_date' => $previousEmploymentRecord['start_date']]);
                            }

                            // check previous employment record end date
                            if (
                                optional($previousEmploymentRecord)['end_date'] &&
                                optional($previousEmploymentRecord)['end_date'] != $previousEmploymentRecordObj->getAttribute("end_date")
                            ) {
                                $previousEmploymentRecordObj->update(['end_date' => $previousEmploymentRecord['end_date']]);
                            }

                            // check previous employment record salary
                            if (
                                optional($previousEmploymentRecord)['salary'] &&
                                optional($previousEmploymentRecord)['salary'] != $previousEmploymentRecordObj->getAttribute("salary")
                            ) {
                                $previousEmploymentRecordObj->update(['salary' => $previousEmploymentRecord['salary']]);
                            }

                            // check previous employment record allowance
                            if (
                                optional($previousEmploymentRecord)['allowance'] &&
                                optional($previousEmploymentRecord)['allowance'] != $previousEmploymentRecordObj->getAttribute("allowance")
                            ) {
                                $previousEmploymentRecordObj->update(['allowance' => $previousEmploymentRecord['allowance']]);
                            }

                            // check previous employment record quit reason
                            if (
                                optional($previousEmploymentRecord)['quit_reason'] &&
                                optional($previousEmploymentRecord)['quit_reason'] != $previousEmploymentRecordObj->getAttribute("quit_reason")
                            ) {
                                $previousEmploymentRecordObj->update(['quit_reason' => $previousEmploymentRecord['quit_reason']]);
                            }
                        }
                    }
                }
            }

            // Employee data (convictions records)
            if (optional($data)['convictions']) {

                $convictionsData = $data['convictions'];

                /*
                 * First we will check if the length of the convictions array is greater than 0,
                 * if so, we will go through each conviction and check if it has an id,
                 * if so, we will update only the fields that are passed,
                 * otherwise we will create a new one. with the passed data.
                 */
                if (count($convictionsData) > 0) {
                    foreach ($convictionsData as $conviction) {
                        // update conviction
                        if (isset(optional($conviction)['conviction_id'])) {

                            // get conviction object
                            $convictionObj = $jobApplication->empData->convictions()->find($conviction['conviction_id']);

                            // check conviction description
                            if (
                                optional($conviction)['description'] &&
                                optional($conviction)['description'] != $convictionObj->getAttribute("description")
                            ) {
                                $convictionObj->update(['description' => $conviction['description']]);
                            }
                        } else {
                            // create conviction
                            $jobApplication->empData->convictions()->create($conviction);
                        }
                    }
                }
            }

            // Employee data (education records)
            if (optional($data)['education']) {

                $educationData = $data['education'];

                /*
                 * First we will check if the length of the education array is greater than 0,
                 * if so, we will go through each education and get education_level_id,
                 * and check if there is a pivot record for this education level,
                 * if so, we will update only the fields that are passed,
                 * otherwise we will create a new one. with the passed data.
                 */

                if (count($educationData) > 0) {
                    foreach ($educationData as $education) {

                        // get the education_level_id
                        $educationLevelId = optional($education)['education_level_id'];

                        // check if there is a pivot record for this education level
                        $educationLevel = $jobApplication->empData->educationLevels()->find($educationLevelId);

                        if ($educationLevel) {

                            // update the existing record in the pivot table, only the passed fields

                            // check university name
                            if (optional($education)['univ_name']) {
                                $educationLevel->pivot->update(['univ_name' => $education['univ_name']]);
                            }

                            // check city
                            if (optional($education)['city']) {
                                $educationLevel->pivot->update(['city' => $education['city']]);
                            }

                            // check start date
                            if (optional($education)['start_date']) {
                                $educationLevel->pivot->update(['start_date' => $education['start_date']]);
                            }

                            // check end date
                            if (optional($education)['end_date']) {
                                $educationLevel->pivot->update(['end_date' => $education['end_date']]);
                            }

                            // check specialize
                            if (optional($education)['specialize']) {
                                $educationLevel->pivot->update(['specialize' => $education['specialize']]);
                            }

                            // check grade
                            if (optional($education)['grade']) {
                                $educationLevel->pivot->update(['grade' => $education['grade']]);
                            }
                        } else {
                            // create a new record in the pivot table
                            $jobApplication->empData->educationLevels()->attach($educationLevelId, $education);
                        }
                    }
                }
            }

            // Employee data (training courses data)
            if (optional($data)['training_courses']) {

                $trainingCoursesData = $data['training_courses'];

                /*
                 * First we will check if the length of the training courses array is greater than 0,
                 * if so, we will go through each training course and check if it has an id,
                 * if so, we will update only the fields that are passed,
                 * otherwise we will create a new one. with the passed data.
                 */
                if (count($trainingCoursesData) > 0) {
                    foreach ($trainingCoursesData as $trainingCourse) {
                        // update training course
                        if (isset(optional($trainingCourse)['training_course_id'])) {

                            // get training course object
                            $trainingCourseObj = $jobApplication->empData->trainingCourses()->find($trainingCourse['training_course_id']);

                            // check training course name
                            if (
                                optional($trainingCourse)['course_name'] &&
                                optional($trainingCourse)['course_name'] != $trainingCourseObj->getAttribute("name")
                            ) {
                                $trainingCourseObj->update(['name' => $trainingCourse['course_name']]);
                            }

                            // check training course institute name
                            if (
                                optional($trainingCourse)['institute_name'] &&
                                optional($trainingCourse)['institute_name'] != $trainingCourseObj->getAttribute("institute_name")
                            ) {
                                $trainingCourseObj->update(['institute_name' => $trainingCourse['institute_name']]);
                            }

                            // check training course city
                            if (
                                optional($trainingCourse)['city'] &&
                                optional($trainingCourse)['city'] != $trainingCourseObj->getAttribute("city")
                            ) {
                                $trainingCourseObj->update(['city' => $trainingCourse['city']]);
                            }

                            // check training course start date
                            if (
                                optional($trainingCourse)['start_date'] &&
                                optional($trainingCourse)['start_date'] != $trainingCourseObj->getAttribute("start_date")
                            ) {
                                $trainingCourseObj->update(['start_date' => $trainingCourse['start_date']]);
                            }

                            // check training course end date
                            if (
                                optional($trainingCourse)['end_date'] &&
                                optional($trainingCourse)['end_date'] != $trainingCourseObj->getAttribute("end_date")
                            ) {
                                $trainingCourseObj->update(['end_date' => $trainingCourse['end_date']]);
                            }

                            // check training course specialize
                            if (
                                optional($trainingCourse)['specialize'] &&
                                optional($trainingCourse)['specialize'] != $trainingCourseObj->getAttribute("specialize")
                            ) {
                                $trainingCourseObj->update(['specialize' => $trainingCourse['specialize']]);
                            }
                        } else {
                            // create training course
                            $jobApplication->empData->trainingCourses()->create($trainingCourse);
                        }
                    }
                }
            }

            // Employee data (skills)
            if (optional($data)['skills']) {

                $skillsData = $data['skills'];

                /*
                 * First we will check if the length of the skills array is greater than 0,
                 * if so, we will go through each skill and check there is a pivot record for this skill,
                 * if so, we will leave it as it is.
                 * otherwise, if the skill is found in the skills table, we will make a record in the pivot table,
                 * otherwise we will create a new skill and then make a record in the pivot table.
                 */
                if (count($skillsData) > 0) {
                    foreach ($skillsData as $skill) {

                        // get the skill name
                        $skillName = optional($skill)['skill_name'];

                        // check if there is a pivot record for this skill
                        $skillObj = $jobApplication->empData->skills()->where('name', $skillName)->first();

                        if (!$skillObj) {
                            // check if the skill is found in the skills table
                            $skillObj = Skill::query()->where('name', $skillName)->first();

                            if (!$skillObj) {
                                // create a new skill
                                $skillObj = Skill::query()->create(['name' => $skillName]);
                            }

                            // create a new record in the pivot table
                            $jobApplication->empData->skills()->attach($skillObj->skill_id);
                        }
                    }
                }
            }

            // Employee data (languages)
            if (optional($data)['languages']) {

                $languagesData = $data['languages'];

                /*
                 * First we will check if the length of the languages array is greater than 0,
                 * if so, we will go through each language object and extract the language name,
                 * if the language name is found in the languages table, we will check if there is a pivot record for this language,
                 * if so, we will update only the fields that are passed,
                 * otherwise if the language is exist but there is no pivot record for it, we will create a new record in the pivot table,
                 * otherwise we will create a new language and then make a record in the pivot table.
                 */
                if (count($languagesData) > 0) {
                    foreach ($languagesData as $language) {

                        // get the language name
                        $languageName = $language['language_name'];

                        // check if the language name is found in the languages table
                        $lang = Language::query()->where('name', $languageName)->first();

                        if (!$lang) {
                            // create a new language
                            $lang = Language::query()->create(['name' => $languageName]);
                        }

                        // check if there is a pivot record for this language
                        $languageObj = $jobApplication->empData->languages()->where('name', $languageName)->first();

                        if ($languageObj) {
                            // check reading
                            if (optional($language)['reading']) {
                                $languageObj->pivot->update(['reading_level' => $language['reading']]);
                            }

                            // check writing
                            if (optional($language)['writing']) {
                                $languageObj->pivot->update(['writing_level' => $language['writing']]);
                            }

                            // check speaking
                            if (optional($language)['speaking']) {
                                $languageObj->pivot->update(['speaking_level' => $language['speaking']]);
                            }
                        } else {
                            // create a new record in the pivot table
                            $jobApplication->empData->languages()->attach($lang->language_id, [
                                'reading_level' => $language['reading'],
                                'writing_level' => $language['writing'],
                                'speaking_level' => $language['speaking'],
                            ]);
                        }
                    }
                }
            }

            // Employee data (computer skills)
            if (optional($data)['computer_skills']) {

                $computerSkillsData = $data['computer_skills'];

                /*
                 * First we will check if the length of the computer skills array is greater than 0,
                 * if so, we will go through each computer skill object and extract the skill name,
                 * if the skill name is found in the computer skills table, we will check if there is a pivot record for this skill,
                 * if so, we will update only the fields that are passed,
                 * otherwise if the skill is exist but there is no pivot record for it, we will create a new record in the pivot table,
                 * otherwise we will create a new skill and then make a record in the pivot table.
                 */
                if (count($computerSkillsData) > 0) {
                    foreach ($computerSkillsData as $computerSkill) {

                        // get the skill name
                        $skillName = $computerSkill['skill_name'];

                        // check if the skill name is found in the computer skills table
                        $skillRecord = ComputerSkill::query()->where('name', $skillName)->first();

                        if (!$skillRecord) {
                            // create a new skill
                            $skillRecord = ComputerSkill::query()->create(['name' => $skillName]);
                        }

                        // check if there is a pivot record for this skill
                        $skillObj = $jobApplication->empData->computerSkills()->where('name', $skillName)->first();

                        if ($skillObj) {
                            // check level
                            if (optional($computerSkill)['level']) {
                                $skillObj->pivot->update(['level' => $computerSkill['level']]);
                            }
                        } else {
                            // create a new record in the pivot table
                            $jobApplication->empData->computerSkills()->attach($skillRecord->computer_skill_id, [
                                'level' => $computerSkill['level'],
                            ]);
                        }
                    }
                }
            }

            // Employee data (references)
            if (optional($data)['references']) {

                $referencesData = $data['references'];

                /*
                 * First we will check if the length of the references array is greater than 0,
                 * if so, we will go through each reference object and see if the reference id is passed,
                 * that means the reference is already exist in the database, and linked to the employee data,
                 * so we will update the reference with the passed data,
                 * otherwise we will create a new reference with the passed data.
                 *
                 * the relation is O2M.
                 */
                if (count($referencesData) > 0) {
                    foreach ($referencesData as $reference) {

                        // check if the reference id is passed
                        if (isset(optional($reference)['reference_id'])) {
                            // update the reference
                            $referenceObj = $jobApplication->empData
                                ->references()
                                ->where('reference_id', $reference['reference_id'])
                                ->first();

                            if ($referenceObj) {

                                // check name
                                if (optional($reference)['name']) {
                                    $referenceObj->update(['name' => $reference['name']]);
                                }

                                // check job
                                if (optional($reference)['job']) {
                                    $referenceObj->update(['job' => $reference['job']]);
                                }

                                // check company
                                if (optional($reference)['company']) {
                                    $referenceObj->update(['company' => $reference['company']]);
                                }

                                // check telephone
                                if (optional($reference)['telephone']) {
                                    $referenceObj->update(['telephone' => $reference['telephone']]);
                                }

                                // check address
                                if (optional($reference)['address']) {
                                    $referenceObj->update(['address' => $reference['address']]);
                                }
                            }
                        } else {
                            // create a new reference
                            $jobApplication->empData->references()->create([
                                'name' => $reference['name'],
                                'job' => $reference['job'],
                                'company' => $reference['company'],
                                'telephone' => $reference['telephone'],
                                'address' => $reference['address'],
                            ]);
                        }
                    }
                }
            }

            // Employee data (certificates)
            if (optional($data)['certificates']) {
                if (count($data['certificates']) > 0) {

                    $certificatesData = $data['certificates'];

                    foreach ($certificatesData as $certificate) {

                        // check if the certificate id is passed
                        if (isset(optional($certificate)['certificate_id'])) {
                            // update the certificate
                            $certificateObj = $jobApplication->empData
                                ->certificates()
                                ->where('certificate_id', $certificate['certificate_id'])
                                ->first();

                            if ($certificateObj) {

                                // check name
                                if (optional($certificate)['certificate_name']) {
                                    $certificateObj->update(['name' => $certificate['certificate_name']]);
                                }

                                // check file
                                if (optional($certificate)['file'] && optional($certificate)['file'] != $certificateObj->file_url) {

                                    // replace the file
                                    $filePath = StorageUtilities::replaceCertificate($certificate['file'], $certificateObj->file_url);

                                    // update the file url
                                    $certificateObj->update(['file_url' => $filePath]);
                                }
                            }
                        } else {

                            // store the file
                            $filePath = StorageUtilities::storeCertificate($certificate['file']);

                            // create a new certificate
                            $jobApplication->empData->certificates()->create([
                                'name' => $certificate['certificate_name'],
                                'file_url' => $filePath,
                            ]);
                        }
                    }
                }
            }


            // delete the records that have ids that are mentioned in the equivalent array

            // dependents
            if (optional($data)['deleted_dependants']) {
                $jobApplication->empData->dependents()->whereIn('dependent_id', $data['deleted_dependants'])->delete();
            }

            // previous employment records
            if (optional($data)['deleted_previous_employment_record']) {
                $jobApplication->empData->previousEmploymentRecords()->whereIn('prev_emp_record_id', $data['deleted_previous_employment_record'])->delete();
            }

            // convictions
            if (optional($data)['deleted_convictions']) {
                $jobApplication->empData->convictions()->whereIn('conviction_id', $data['deleted_convictions'])->delete();
            }

            // education
            if (optional($data)['deleted_education']) {
                // the education records are the ids of the entries in the pivot table
                $jobApplication->empData->educationLevels()->wherePivotIn('education_level_id', $data['deleted_education'])->detach();
            }

            // training courses
            if (optional($data)['deleted_training_courses']) {
                $jobApplication->empData->trainingCourses()->whereIn('training_course_id', $data['deleted_training_courses'])->delete();
            }

            // skills
            if (optional($data)['deleted_skills']) {
                // the skills records are the ids of the entries in the pivot table
                $jobApplication->empData->skills()->wherePivotIn('skill_id', $data['deleted_skills'])->detach();
            }

            // languages
            if (optional($data)['deleted_languages']) {
                // Get the empData's languages relationship and use the wherePivotIn method
                // to filter by the given language IDs and detach the matching records
                $jobApplication->empData->languages()->wherePivotIn('language_id', $data['deleted_languages'])->detach();
            }

            // computer skills
            if (optional($data)['deleted_computer_skills']) {
                // the computer skills records are the ids of the entries in the pivot table
                $jobApplication->empData->computerSkills()->wherePivotIn('computer_skill_id', $data['deleted_computer_skills'])->detach();
            }

            // references
            if (optional($data)['deleted_references']) {
                $jobApplication->empData->references()->whereIn('reference_id', $data['deleted_references'])->delete();
            }

            // certificates
            if (optional($data)['deleted_certificates']) {

                // get the file url from the database for the given certificate ids
                $fileUrls = $jobApplication->empData->certificates()->whereIn('certificate_id', $data['deleted_certificates'])->pluck('file_url')->toArray();

                // delete the files from the storage for the given file urls
                StorageUtilities::deleteFiles($fileUrls);

                // delete the records from the database
                $jobApplication->empData->certificates()->whereIn('certificate_id', $data['deleted_certificates'])->delete();
            }


            DB::commit();


            // fetch the updated version of the job application, and return it
            if (isset($jobApplication->job_app_id)) {
                return $this->getJobApplicationById($jobApplication->job_app_id);
            }

            return $jobApplication;
        } catch (Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    public function deleteJobApplication($id): Builder|Model
    {
        $jobApplication = JobApplication::findOrFail($id);

        // extract the file urls from the certificates
        $fileUrls = $jobApplication->empData->certificates()->pluck('file_url')->toArray();

        // get the personal photo url
        $personalPhotoUrl = $jobApplication->empData->personal_photo;

        // create the complete array of file urls
        $fileUrls[] = $personalPhotoUrl;

        // delete the files from the storage for the given file urls
        StorageUtilities::deleteFiles($fileUrls);

        // delete the job application
        $jobApplication->delete();

        return $jobApplication;
    }
}
