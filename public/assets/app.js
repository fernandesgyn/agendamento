document.addEventListener('DOMContentLoaded',()=>{
  const brandLogo=document.querySelector('.brand img');
  if(brandLogo){
    brandLogo.src='/assets/logo.svg';
    brandLogo.alt='AGEHAB';
    brandLogo.style.width='88px';
    brandLogo.style.height='48px';
    brandLogo.style.objectFit='contain';
    brandLogo.style.background='transparent';
    brandLogo.style.borderRadius='0';
    brandLogo.style.padding='0';
  }

  const logoResponsiveStyle=document.createElement('style');
  logoResponsiveStyle.textContent='@media (min-width:650px){.brand img{width:104px!important;height:56px!important}}';
  document.head.appendChild(logoResponsiveStyle);

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
  if(initiallySelected) selectDate(initiallySelected);

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
