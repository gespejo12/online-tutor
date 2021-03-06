<?php

namespace App\Http\Controllers;

use App\Section;
use App\User;
use App\TeacherData;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {

            \DB::beginTransaction();

            $section_data = $request->all();
            $section_data['teacher_id'] = \Auth::id();
            $section = Section::create($section_data);
            $section->section_code = strtoupper(str_random(3)) . '-000-' . $section->id;
            $section->save();
            \DB::commit();
            $msg = 'Add Section Success!';
        } catch (\Exception $e) {
            \DB::rollBack();
            $error_message = $e->getMessage();
        }

        return compact('msg', 'error_message');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Section  $section
     * @return \Illuminate\Http\Response
     */
    public function show(Section $section)
    {
        return $section;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Section  $section
     * @return \Illuminate\Http\Response
     */
    public function edit(Section $section)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Section  $section
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Section $section)
    {
        try {

            \DB::beginTransaction();
            $section->update($request->all());
            \DB::commit();
            $msg = 'Update Section Success!';
        } catch (\Exception $e) {
            \DB::rollBack();
            $error_message = $e->getMessage();
        }
        return compact('msg', 'error_message');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Section  $section
     * @return \Illuminate\Http\Response
     */
    public function destroy(Section $section)
    {
        try {

            \DB::beginTransaction();
            $section->delete();
            \DB::commit();
            $msg = 'Delete Success!';
        } catch (\Exception $e) {
            \DB::rollBack();
            $error_message = $e->getMessage();
        }

        return compact('msg', 'error_message');
    }

    public function uploadFile(Request $request)
    {
         \Session::flash('tab', 'tab_file_manager');
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $name = $file->getClientOriginalName();
            $section = Section::where('section_code', $request->section_code)->first();
            $data = [
                'file_name' => str_slug($name),
                'file_destination' => ("/uploads/" . $section->section_code . '/' . $name),
            ];
            $in = [
                'section_id' => $section->id,
                'key' => 'files',
                'value' => json_encode($data),
            ];
            $td = new TeacherData;
            $td->fill($in)->save();
            $file->move("uploads/" . $section->section_code, $file->getClientOriginalName());
            return redirect()->back();
        }

        return redirect()->back();
    }

    public function storeExam(Request $request)
    {
        try {
            \DB::beginTransaction();
            $data = $request->all();
            $teacher_data = [];
            $teacher_data['section_id'] = $data['section_id']; 
            
            unset($data['section_id']); 
            $teacher_data['value'] = json_encode($data);
            $teacher_data['key']    = 'exams';
            TeacherData::create($teacher_data);
            \DB::commit();
            $msg = 'Add Success!';
        } catch (\Exception $e) {
            \DB::rollBack();
            $error_message = $e->getMessage();
        }
        \Session::flash('tab', 'tab_exam');
        return compact('msg', 'error_message');
    }

    public function showExam($id)
    {
        $exam = TeacherData::find($id);
        $exam = json_decode($exam->value);
        $exam_id = $id;
        return compact('exam', 'exam_id');
    }

    public function updateExam(Request $request)
    {
        try {

            \DB::beginTransaction();

            $data = $request->all();
            $exam_id = $data['exam_id'];
            //Remove other data
            unset($data['section_id']); 
            unset($data['exam_id']); 
            //update
            $exam = TeacherData::find($exam_id);
            $exam->value = json_encode($data);
            $exam->save();
            \DB::commit();
            $msg = 'Update Success!';
        } catch (\Exception $e) {
            \DB::rollBack();
            $error_message = $e->getMessage();
        }
        \Session::flash('tab', 'tab_exam');
        return compact('msg', 'error_message');
    }

    public function destroyExam($id)
    {
        try {

            \DB::beginTransaction();
            $exam = TeacherData::find($id);
            $exam->delete();
            \DB::commit();
            $msg = 'Delete Success!';
        } catch (\Exception $e) {
            \DB::rollBack();
            $error_message = $e->getMessage();
        }
        \Session::flash('tab', 'tab_exam');
        return compact('msg', 'error_message');
    }

    public function doneExam(Request $request)
    {
        try {

            $data = $request->all();
            \DB::beginTransaction();

            //get student answer
            $exam_result            = [];
            $exam                   = TeacherData::find($data['id']);
            $value                  = json_decode($exam->value);
            $total_question =       count((array)$value->question);
            $exam_answer_key        = $value->answer;
            $exam_answer_choice     = $value->choice;
            $student_answers        = TeacherData::where('parent_key',$data['id'])->where('key','student_answer')->get();

            foreach ($student_answers as $key =>  $student) {
                $student_data = json_decode($student->value);
                $student = json_decode($student);
                $exam_result[$key]['student_id'] = $student_data->student_id;

                $student_score = 0;
                foreach ($student_data->answers as $student_value) {
                   
                   //get question #
                    $question_no = intval(preg_replace('/[^0-9]+/', '', $student_value->name), 10);

                    //get the  answer in qusetion #
                    $right_answer = $exam_answer_key->$question_no;

                    //change Letter Key to index #
                    switch ($right_answer[0]) {
                        case 'A':
                            $right_answer_index = 0;
                            break;
                        case 'B':
                            $right_answer_index = 1;
                            break;
                        case 'C':
                            $right_answer_index = 2;
                            break;
                    }

                    //get the answer value
                    $right_answer_value = $exam_answer_choice->$question_no[$right_answer_index];

                    //compair stundent answer to right answer
                    

                    if ($right_answer_value == $student_value->value) {
                        
                        $student_score++;
                    }
                    
                }
                $exam_result[$key]['student_score'] = $student_score  . ' out of ' . $total_question;
            }

            //save results
            TeacherData::create([
                'section_id'    => $exam->section_id,
                'key'           => 'exam_result',
                'parent_key'    => $data['id'],
                'value'         => json_encode($exam_result)
            ]);   

            $value->status = 'done';
            $exam->value =  json_encode($value);
            $exam->save();
            \DB::commit();
            $msg = 'Done Exam!';
        } catch (\Exception $e) {
            \DB::rollBack();
            $error_message = $e->getMessage();
        }
        \Session::flash('tab', 'tab_exam');
        return compact('msg', 'error_message');
    }

    public function showExamResult($id)
    {
        $exam_result = TeacherData::where('parent_key', $id)->where('key', 'exam_result')->first();
        $results =  json_decode($exam_result->value);
        $tbody = '';
        foreach ($results as $key => $student_data) {

            $user_data = User::find($student_data->student_id);
            $tbody .= '<tr>';
            $tbody .= '<td>' . $user_data->name . '</td>';
            $tbody .= '<td>' . $student_data->student_score . '</td>';
            $tbody .= '</tr>';
        }
        return response()->json($tbody);
    }

}
