@extends('layouts.app')

@section('title', 'How many hours of wedding photography do you need?')
@section('meta_description', 'Answer a few quick questions and get a straight recommendation for wedding photography coverage, with a sample timeline for your day.')
@section('robots', 'noindex,nofollow')

@section('content')
<style>
    .quiz{max-width:640px;margin:0 auto;padding:48px 20px 80px}
    .quiz h1{font-weight:400;line-height:1.15;margin:0 0 12px}
    .quiz__sub{color:#5a4d42;margin:0 0 24px}
    .quiz__btn{display:inline-block;background:#2c2018;color:#fffdfa;border:0;padding:14px 26px;font:inherit;cursor:pointer}
    .quiz__btn[disabled]{opacity:.4;cursor:default}
    .quiz__link{background:none;border:0;padding:0;font:inherit;color:#7a6555;text-decoration:underline;cursor:pointer;margin-right:18px}
    .quiz__small{font-size:13px;color:#7a6555;margin:10px 0 0}
    .quiz__photos{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin:28px 0 0}
    .quiz__photos img{width:100%;aspect-ratio:4/5;object-fit:cover;display:block}
    .quiz__bar{height:4px;background:#efe3d7;margin:0 0 28px}
    .quiz__bar i{display:block;height:100%;background:#2c2018;width:0;transition:width .25s}
    .quiz__opts{display:grid;gap:10px;margin:0 0 22px}
    .quiz__opt{display:flex;gap:10px;align-items:center;border:1px solid #d9c8b6;padding:14px 16px;cursor:pointer;background:#fffdfa}
    .quiz__opt:has(input:checked){border-color:#2c2018;background:#f6f1ea}
    .quiz input[type=text],.quiz input[type=email]{width:100%;padding:12px;border:1px solid #d9c8b6;font:inherit;box-sizing:border-box;margin:0 0 20px}
    .quiz__err{color:#9a3b2a;margin:0 0 12px}
    .quiz__card{border:1px solid #e6d9ca;background:#fffdfa;padding:24px;margin:18px 0}
    .quiz__tl{width:100%;border-collapse:collapse}
    .quiz__tl td{padding:5px 12px 5px 0;vertical-align:top}.quiz__tl td:first-child{white-space:nowrap;color:#7a6555}
    .quiz [hidden]{display:none!important}
    .quiz__hp{position:absolute;left:-9999px}
</style>

<div class="quiz" id="quiz">
    {{-- Landing --}}
    <section data-screen="landing">
        <p class="eyebrow">Wedding coverage quiz</p>
        <h1>How many hours of wedding coverage do you actually need?</h1>
        <p class="quiz__sub">Answer 8 quick questions. Get a straight recommendation and a sample timeline for your day. Free, about 2 minutes.</p>
        <button type="button" class="quiz__btn" id="quiz-start">Find my coverage</button>
        <p class="quiz__small">Free · 2 minutes · result by email</p>
        <div class="quiz__photos">
            <img src="/images/quiz/sunset.jpg" alt="A couple at sunset on Clearwater Beach" loading="lazy">
            <img src="/images/quiz/aisle.jpg" alt="A couple kiss at the end of a garden aisle" loading="lazy">
            <img src="/images/quiz/kiss.jpg" alt="A couple kiss in a courtyard garden" loading="lazy">
        </div>
        <p class="quiz__small">Rated 5.0 on Google, Zola and WeddingWire.</p>
    </section>

    <form id="quiz-form" hidden novalidate>
        <div class="quiz__hp"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="quiz__bar"><i id="quiz-bar"></i></div>

        <section data-step="name">
            <h1>First, what should I call you?</h1>
            <input type="text" name="first_name" autocomplete="given-name" maxlength="80" aria-label="First name">
        </section>

        <section data-step="day" hidden>
            <h1>What kind of day is it?</h1>
            <div class="quiz__opts">
                @foreach (['full' => 'Full wedding', 'small' => 'Small ceremony', 'elopement' => 'Elopement', 'unsure' => 'Not sure yet'] as $k => $l)
                    <label class="quiz__opt"><input type="radio" name="day" value="{{ $k }}"> {{ $l }}</label>
                @endforeach
            </div>
        </section>

        <section data-step="guests" hidden>
            <h1>About how many guests?</h1>
            <div class="quiz__opts">
                @foreach (['u25' => 'Under 25', '25-75' => '25 to 75', '75-150' => '75 to 150', '150plus' => '150 or more'] as $k => $l)
                    <label class="quiz__opt"><input type="radio" name="guests" value="{{ $k }}"> {{ $l }}</label>
                @endforeach
            </div>
        </section>

        <section data-step="locations" hidden>
            <h1>Are the ceremony and reception in the same place?</h1>
            <div class="quiz__opts">
                @foreach (['same' => 'Yes, same place', 'short' => 'No, a short drive', 'far' => 'No, far apart'] as $k => $l)
                    <label class="quiz__opt"><input type="radio" name="locations" value="{{ $k }}"> {{ $l }}</label>
                @endforeach
            </div>
        </section>

        <section data-step="moments" hidden>
            <h1>Which of these do you want photographed?</h1>
            <p class="quiz__small" style="margin:0 0 12px">Pick all that apply.</p>
            <div class="quiz__opts">
                @foreach ($moments as $k => [$l])
                    <label class="quiz__opt"><input type="checkbox" name="moments[]" value="{{ $k }}"> {{ $l }}</label>
                @endforeach
            </div>
        </section>

        <section data-step="start" hidden>
            <h1>When does the ceremony start?</h1>
            <div class="quiz__opts">
                @foreach (['morning' => 'Morning', 'afternoon' => 'Afternoon', 'evening' => 'Evening'] as $k => $l)
                    <label class="quiz__opt"><input type="radio" name="start" value="{{ $k }}"> {{ $l }}</label>
                @endforeach
            </div>
        </section>

        <section data-step="worry" hidden>
            <h1>What's your biggest worry?</h1>
            <div class="quiz__opts">
                @foreach (['time' => 'Running out of time for portraits', 'posing' => 'Awkward posing', 'family' => 'Missing family moments', 'budget' => 'Staying in budget'] as $k => $l)
                    <label class="quiz__opt"><input type="radio" name="worry" value="{{ $k }}"> {{ $l }}</label>
                @endforeach
            </div>
        </section>

        <section data-step="second" hidden>
            <h1>Want a second photographer?</h1>
            <div class="quiz__opts">
                @foreach (['yes' => 'Yes', 'no' => 'No', 'unsure' => 'Not sure'] as $k => $l)
                    <label class="quiz__opt"><input type="radio" name="second" value="{{ $k }}"> {{ $l }}</label>
                @endforeach
            </div>
        </section>

        <section data-step="when" hidden>
            <h1>When and where? <span class="quiz__small">(optional)</span></h1>
            <input type="text" name="wedding_date" placeholder="Month and year, like October 2027" maxlength="80" aria-label="Wedding month and year">
            <input type="text" name="venue" placeholder="Venue or area" maxlength="160" aria-label="Venue">
        </section>

        <section data-step="email" hidden>
            <h1>Where should I send your plan?</h1>
            <input type="email" name="email" autocomplete="email" maxlength="255" aria-label="Email">
            <label style="display:flex;gap:10px;align-items:flex-start;margin:0 0 20px"><input type="checkbox" name="consent" value="1" style="margin-top:5px"> <span>Email me my result. I understand my answers are stored so Donald can follow up if I reach out. One email, with an unsubscribe link.</span></label>
        </section>

        <p class="quiz__err" id="quiz-err" role="alert" hidden></p>
        <button type="button" class="quiz__link" id="quiz-back">Back</button>
        <button type="button" class="quiz__btn" id="quiz-next">Next</button>
    </form>

    <section id="quiz-result" hidden aria-live="polite"></section>
</div>

<script>
(function () {
    var steps = ['name','day','guests','locations','moments','start','worry','second','when','email'];
    var form = document.getElementById('quiz-form'), landing = document.querySelector('[data-screen=landing]');
    var next = document.getElementById('quiz-next'), back = document.getElementById('quiz-back');
    var bar = document.getElementById('quiz-bar'), err = document.getElementById('quiz-err'), res = document.getElementById('quiz-result');
    var i = 0;
    function esc(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;}
    function active(){return steps.filter(function(s){ // small ceremonies skip guest/location/start questions
        var d = form.elements.day && form.querySelector('[name=day]:checked');
        if (d && (d.value==='elopement'||d.value==='small') && ['guests','locations','moments','start','second'].indexOf(s)>-1) return false;
        return true;});}
    function show(){var list=active();var cur=list[i];
        steps.forEach(function(s){form.querySelector('[data-step='+s+']').hidden = s!==cur;});
        bar.style.width=((i+1)/list.length*100)+'%'; back.hidden=i===0;
        next.textContent = cur==='email' ? 'Show my plan' : (cur==='when' ? 'Next' : 'Next'); err.hidden=true;}
    function valid(cur){
        if(cur==='name') return form.first_name.value.trim()!==''||'Tell me what to call you.';
        if(['day','guests','locations','start','worry','second'].indexOf(cur)>-1) return !!form.querySelector('[name='+cur+']:checked')||'Pick one to continue.';
        if(cur==='email'){ if(!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(form.email.value.trim())) return 'That email does not look right.'; if(!form.consent.checked) return 'Please tick the box so I can email your plan.'; }
        return true;}
    document.getElementById('quiz-start').onclick=function(){landing.hidden=true;form.hidden=false;i=0;show();window.scrollTo(0,0);};
    back.onclick=function(){if(i>0){i--;show();}};
    next.onclick=function(){var list=active(),cur=list[i],v=valid(cur);
        if(v!==true){err.textContent=v;err.hidden=false;return;}
        if(i<list.length-1){i++;show();return;}
        submit();};
    function submit(){next.disabled=true;next.textContent='One moment...';
        var fd=new FormData(form);
        fetch('{{ route('quiz.store') }}',{method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'},body:fd})
        .then(function(r){return r.json().then(function(j){return {ok:r.ok,j:j};});})
        .then(function(o){ if(!o.ok){throw o.j;} render(o.j); })
        .catch(function(e){next.disabled=false;next.textContent='Show my plan';err.textContent=(e&&e.message)||'Something went wrong. Please try again.';err.hidden=false;});}
    function render(j){form.hidden=true;res.hidden=false;var name=esc(form.first_name.value.trim());var h='';
        if(!j.tier){h='<h1>Thanks.</h1>';}
        else if(j.result==='elopement'){
            h='<h1>'+name+', that is a different kind of day.</h1><p class="quiz__sub">'+esc(j.tier.intro)+'</p><p><a class="quiz__btn" href="'+esc(j.tier.url)+'">See elopement coverage</a></p><p><a href="'+esc(j.inquiry_url)+'">Or ask about your date</a></p>';
        } else {
            h='<h1>'+name+', here\'s what we\'d plan for you: '+j.tier.hours+' hours.</h1><p class="quiz__sub">'+esc(j.tier.label)+' coverage, from '+esc(j.tier.price)+'. '+esc(j.tier.intro)+'</p><p>'+esc(j.why)+'</p><div class="quiz__card"><strong>A sample day (for a 4:30 PM ceremony)</strong><table class="quiz__tl">'+j.tier.timeline.map(function(r){return '<tr><td>'+esc(r[0])+'</td><td>'+esc(r[1])+'</td></tr>';}).join('')+'</table>'+(j.tier.gap?'<p>'+esc(j.tier.gap)+'</p>':'')+'</div>'+(j.second?'<p>'+esc(j.second)+'</p>':'')+'<p>Every wedding includes the full edited gallery, and we sit down and look through it together on a big screen.</p><p><a class="quiz__btn" href="'+esc(j.inquiry_url)+'">Check my date</a></p>'+(j.emailed?'<p class="quiz__small">I also emailed this to you.</p>':'');
        }
        res.innerHTML=h;window.scrollTo(0,0);}
})();
</script>
@endsection
