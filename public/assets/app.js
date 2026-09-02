document.addEventListener('DOMContentLoaded',()=>{
  const cpfInput=document.getElementById('cpf');
  const birthDateInput=document.getElementById('birth_date');

  const onlyDigits=value=>value.replace(/\D/g,'');

  const maskCpf=value=>{
    const digits=onlyDigits(value).slice(0,11);
    if(digits.length<=3)return digits;
    if(digits.length<=6)return `${digits.slice(0,3)}.${digits.slice(3)}`;
    if(digits.length<=9)return `${digits.slice(0,3)}.${digits.slice(3,6)}.${digits.slice(6)}`;
    return `${digits.slice(0,3)}.${digits.slice(3,6)}.${digits.slice(6,9)}-${digits.slice(9)}`;
  };

  const maskBirthDate=value=>{
    const digits=onlyDigits(value).slice(0,8);
    if(digits.length<=2)return digits;
    if(digits.length<=4)return `${digits.slice(0,2)}/${digits.slice(2)}`;
    return `${digits.slice(0,2)}/${digits.slice(2,4)}/${digits.slice(4)}`;
  };

  cpfInput?.addEventListener('input',()=>{
    cpfInput.value=maskCpf(cpfInput.value);
  });

  birthDateInput?.addEventListener('input',()=>{
    birthDateInput.value=maskBirthDate(birthDateInput.value);
  });

  const dateButtons=[...document.querySelectorAll('.date-card')];
  const slotButtons=[...document.querySelectorAll('.slot[data-slot-date]')];
  const selectedDayLabel=document.getElementById('selectedDayLabel');

  const selectDate=(btn)=>{
    const selectedDate=btn.dataset.date;

    dateButtons.forEach(x=>{
      const active=x===btn;
      x.classList.toggle('selected',active);
      x.setAttribute('aria-pressed',active?'true':'false');
    });

    slotButtons.forEach(slot=>{
      slot.hidden=slot.dataset.slotDate!==selectedDate;
    });

    if(selectedDayLabel){
      selectedDayLabel.textContent=btn.dataset.dateLabel||'';
    }
  };

  dateButtons.forEach(btn=>btn.addEventListener('click',()=>selectDate(btn)));
  const initiallySelected=document.querySelector('.date-card.selected')||dateButtons[0];
  if(initiallySelected)selectDate(initiallySelected);

  const modal=document.getElementById('confirmModal');
  const choice=document.getElementById('modalChoice');
  const slotInput=document.getElementById('modalSlotId');

  document.querySelectorAll('.slot').forEach(btn=>btn.addEventListener('click',()=>{
    if(!modal)return;
    slotInput.value=btn.dataset.slotId;
    choice.textContent=`${btn.dataset.dateLabel} às ${btn.dataset.time}`;
    modal.hidden=false;
    document.body.style.overflow='hidden';
  }));

  const closeModal=()=>{
    if(!modal)return;
    modal.hidden=true;
    document.body.style.overflow='';
  };

  document.querySelectorAll('[data-close]').forEach(btn=>btn.addEventListener('click',closeModal));
  modal?.addEventListener('click',e=>{if(e.target===modal)closeModal()});
  document.addEventListener('keydown',e=>{if(e.key==='Escape'&&modal&&!modal.hidden)closeModal()});
});
