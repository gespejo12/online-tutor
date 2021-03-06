<?php

namespace App\Http\Controllers;

use App\Section;
use App\StudentAttendance;
use App\StudentSection;
use App\TeacherData;
use App\User;
use Illuminate\Http\Request;

class ICOapiController extends Controller
{
    public function checkIp(Request $request)
    {
        $ip = gethostbyname(getHostname());
        if ($request->ip_address === $ip) {
            return response()->json([
                'error' => 'ok',
            ], 200);
        }
        return response()->json([
            'error' => 'not',
        ], 400);
    }

    public function getSubj(Request $request)
    {
        $sections = StudentSection::where('student_id', $request->student_id)->get();
        $s = [];
        $ats = [];
        $date = date('Y-m-d');
        foreach ($sections as $key => $sec) {
            $s[] = Section::where('id', $sec->section_id)->first();
            $at = StudentAttendance::where('date', $date)
                ->where('section_code', $s[$key]->section_code)
                ->where('student_id', $request->student_id)->first();

            if ($at) {
                $ats[] = true;
            } else {
                $ats[] = false;
            }
        }
        $data = [];
        $datas = [];

        foreach ($s as $key => $section) {
            $user = User::find($section->teacher_id);
            $data = [
                'id' => $section->id,
                'teacher_id' => $section->teacher_id,
                'teacher_name' => $user->name,
                'section_code' => $section->section_code,
                'section_name' => $section->section_name,
                'attend' => $ats[$key],
            ];
            $datas[] = $data;
        }
        return response()->json($datas);
    }

    public function attendance(Request $request)
    {

        if (!isset($request->student_id) || !isset($request->section_code)) {
            return response()->json([
                'error' => 'not',
            ], 400);
        }
        $date = date('Y-m-d');

        $exist = StudentAttendance::where('date', $date)
            ->where('section_code', $request->section_code)
            ->where('student_id', $request->student_id)->first();
        if ($exist) {
            return response()->json($exist);
        }
        $attendance = new StudentAttendance;
        $request->request->set('date', $date);
        $attendance->fill($request->all())->save();
        return response()->json($attendance);
    }

    public function enSect(Request $request)
    {
        if (!isset($request->subject_code) || !isset($request->student_id)) {
            return response()->json([
                'error' => 'not',
            ], 400);
        }
        $section = Section::where('section_code', $request->subject_code)->first();
        if (empty($section)) {
            return response()->json([
                'error' => 'section_not_exist',
                'message' => 'Section does not exist',
            ], 404);
        }
        $exist = StudentSection::where('student_id', $request->student_id)->where('section_id', $section->id)->first();
        if ($exist) {
            return response()->json([
                'error' => 'already_enrolled',
                'message' => 'Already enrolled',
            ], 400);
        }
        $add = new StudentSection;
        $add->student_id = $request->student_id;
        $add->section_id = $section->id;
        $add->save();

        $user = User::find($section->teacher_id);
        $data = [
            'id' => $section->id,
            'teacher_id' => $section->teacher_id,
            'teacher_name' => $user->name,
            'section_code' => $section->section_code,
            'section_name' => $section->section_name,
        ];

        return response()->json($data);
    }

    public function getFiles(Request $request)
    {
        if (!isset($request->section_code)) {
            return response()->json([
                'error' => 'section_do_not_exist',
                'message' => 'Section do not exist.',
            ], 400);
        }
        $section = Section::where('section_code', $request->section_code)->first();
        if (empty($section)) {
            return response()->json([
                'error' => 'section_does_not_exist',
                'message' => 'Section does not exist.',
            ], 404);
        }
        $files = TeacherData::where('section_id', $section->id)->where('key', 'files')->get();
        $data = [];
        $datas = [];
        foreach ($files as $file) {
            $data = [
                'section_code' => $section->section_code,
                'section_name' => $section->section_name,
                'teacher_id' => $section->section_name,
                'file_name' => json_decode($file->value)->file_name,
                'file_path' => json_decode($file->value)->file_destination,
            ];
            $datas[] = $data;
        }
        return response()->json($datas);
    }

    public function getQuiz(Request $request)
    {
        if (!isset($request->section_code)) {
            return response()->json([
                'error' => 'section_do_not_exist',
                'message' => 'Section do not exist.',
            ], 400);
        }
        $section = Section::where('section_code', $request->section_code)->first();
        if (empty($section)) {
            return response()->json([
                'error' => 'section_does_not_exist',
                'message' => 'Section does not exist.',
            ], 404);
        }

        $exams = TeacherData::where('section_id', $section->id)->where('key', 'exams')->where('value', 'like', '%published%')->get();

        $datas = [];
        $data = [];
        foreach ($exams as $exam) {
            $ex = json_decode($exam->value);
            $questions = [];
            foreach ($ex->question as $key => $val) {
                // get questions
                $questions[$key]['question'] = $val;
                foreach ($ex->choice as $key2 => $valu) {
                    // get choices
                    if ($key == $key2) {
                        $choices = $valu;
                    }
                }
                $questions[$key]['choices'] = $choices;
            }
            $datas[] = [
                'id' => $exam->id,
                'exam_name' => $ex->exam_name,
                'questions' => $questions,
            ];
        }
        return response()->json($datas);
    }

    public function getAnswers(Request $request)
    {

        if ($request->all() === null) {
            return response()->json([
                'error' => 'invalid_request',
                'message' => 'Request is empty',
            ], 400);
        }
        $student_id = $request->student_id;
        $exam_id = $request->id;
        $answers = $request->answers;
        $answers['student_id'] = $student_id;
        $datas = [];
        $chen = TeacherData::find($exam_id);
        $checking = TeacherData::where('parent_key', $exam_id)->where('section_id', $chen->section_id)->where('key', 'student_answer')->where('value', 'like', '%"student_id":' . $student_id . '%')->first();
        if ($checking) {
            return response()->json([
                'error' => 'already_taken',
                'message' => 'Exam is already taken.',
            ], 400);
        }
        $data = [
            'parent_key' => $exam_id,
            'section_id' => $chen->section_id,
            'key' => 'student_answer',
            'value' => json_encode($answers),
        ];

        $td = new TeacherData;
        $td->create($data);

        return response()->json($request->all());
    }
}
