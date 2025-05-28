# 쿠키페이 플러그인 (쿠키페이 영카트 결제 연동 플러그인)

- 영카트 5.6.8 > 2025.01.14 기준 최신버전 지원

- 결제 로그 기록  
    - DOCUMENT ROOT의 상위 디렉토리에 logs 디렉토리 생성  
    - logs 디렉토리에 707 권한 부여  

- 쿠키페이 플러그인 관련 테이블  
    - 자동으로 생성되며 "cookiepay_"와 같은 Prefix로 시작합니다.  

- https 설정 안내  
      - /cookiepay/cookiepay.constants.php  
      - 위 파일에서 아래 코드를 찾아 변경 (https 사용시: true, 미사용시: false)  
      ```  
      define('COOKIEPAY_USE_HTTPS', true);
      ```  
  
- 결제 통지 설정 안내 
    - 아래의 URL을 쿠키페이 담당자에게 전달해 [PG 통지 URL] 설정을 요청해 주세요.    
      ```  
      https://[플러그인설치도메인]/cookiepay/result.noti.php  
      ```  

- 영카트를 커스터마이징 하지 않은 경우  
    - 아래 디렉토리와 파일을 영카트 설치 디렉토리에 Copy & Paste(덮어쓰기) 
    - (cookiepay-plugin)  
        - /adm  
        - /cookiepay  
        - /shop  
        - /mobile  
        - /theme  
        - /common.php  

- 영카트를 커스터마이징한 경우  
        - Copy & Paste(붙여넣기)

        - /cookiepay 
        - /adm/shop_admin/cookiepay.ajax.php
        - /adm/shop_admin/cookiepay.cancel.php 
        - /adm/shop_admin/cookiepay.configformupdate.php
        - /adm/shop_admin/cookiepay.pgconfig.php
        - /adm/shop_admin/cookiepay.pgresult.php
        - /shop/SETTLE_COOKIEPAY_AL.inc.php
        - /shop/SETTLE_COOKIEPAY_DN.inc.php
        - /shop/SETTLE_COOKIEPAY_KI.inc.php
        - /shop/SETTLE_COOKIEPAY_KW.inc.php
        - /shop/SETTLE_COOKIEPAY_TS.inc.php
        - /shop/SETTLE_COOKIEPAY_WP.inc.php
        - /shop/COOKIEPAY_AL
        - /shop/COOKIEPAY_DN
        - /shop/COOKIEPAY_KI
        - /shop/COOKIEPAY_KW
        - /shop/COOKIEPAY_TS
        - /shop/COOKIEPAY_WP
        - /mobile/shop/SETTLE_COOKIEPAY_AL.inc.php
        - /mobile/shop/SETTLE_COOKIEPAY_DN.inc.php
        - /mobile/shop/SETTLE_COOKIEPAY_KI.inc.php
        - /mobile/shop/SETTLE_COOKIEPAY_KW.inc.php
        - /mobile/shop/SETTLE_COOKIEPAY_TS.inc.php
        - /mobile/shop/SETTLE_COOKIEPAY_WP.inc.php
        - /mobile/shop/COOKIEPAY_AL
        - /mobile/shop/COOKIEPAY_DN
        - /mobile/shop/COOKIEPAY_KI
        - /mobile/shop/COOKIEPAY_KW
        - /mobile/shop/COOKIEPAY_TS
        - /mobile/shop/COOKIEPAY_WP
        - /lib/shop.lib.php
            
    - 코드 추가  
        - /adm/admin.menu400.shop_1of2.php
        - /adm/admin.menu990.cookiepay.php
        - /adm/shop_admin/configform.php
        - /adm/shop_admin/configformupdate.php
        - /adm/shop_admin/orderform.php
        - /adm/shop_admin/orderformcartupdate.php
        - /adm/shop_admin/orderlist.php
        - /adm/shop_admin/itemform.php
        - /adm/shop_admin/itemformupdate.php
        - /shop/orderform.sub.php
        - /shop/orderformupdate.php
        - /shop/orderinquiryview.php
        - /shop/cartupdate.php
        - /mobile/shop/orderform.sub.php
        - /mobile/shop/orderformupdate.php
        - /mobile/shop/orderinquiryview.php
        - /theme/basic/shop/orderinquiryview.php
        - /theme/basic/shop/mypage.php
        - /lib/shop.lib.php
        - /mobile/skin/shop/basic/item.form.skin.php
        - /skin/shop/basic/item.form.skin.php
        - /skin/shop/basic/item.info.skin.php
        - /skin/shop/basic/list.10.skin.php
        ```  
        ❗ 코드 추가는 영카트 원본파일이 수정된 경우이므로 아래와 같은 주석 구문을 검색해  
         해당 코드블록을 복사해 붙여넣어 주시기 바랍니다.

        // s: cookiepay-plugin -> "s:" 코드블록 시작을 의미
        // e: cookiepay-plugin -> "e:" 코드블록 종료를 의미

        (주의: 코드블록은 여러 개가 존재할 수 있습니다)
        ```  


<br><br>
## 업데이트 히스토리

■ 2025.05.26<br>
- 영카트 신용카드(비인증) 수기결제 사용자 이용가능 설정 기능 추가<br>
Git 에서 Update No 20250526 #1 일자 > 업데이트 필요<br><br>

■ 2025.05.23<br>
- 영카트 정기(구독) 기능추가<br>
Git 에서 Update No 20250523 일자 > 업데이트 필요<br>
기능 추가 방법 : https://www.cookiepayments.com/iroboard/view?bId=API_Devolper&wr_id=4668<br><br>

■ 2025.05.14<br>
 - 영카트 5.6.8(최신버전 지원 2025.01.14 기준)<br>
Git 에서 20250104 일자 > 업데이트 필요<br><br>

1. 쿠키페이 연동 아이디, 시크릿 키 > 영카트 5.6.8 버전(2025.01.14 기준) 에서 저장 안 되는 문제 수정<br>
2. 최신버전 지원을 위해 > 기존 영카트 플러그인 파일 : common.php 파일 삭제.<br><br>

■ 2024.03.15<br>
- 부분 취소 기능 추가<br>
Git 에서 20240315 일자 > 업데이트 필요<br><br>

주문내역 > 상세보기 > 주문상품 2건 이상시 > 부분 상품취소(PG) 가능하도록 기능 업데이트 되었습니다.<br>
- 업데이트 파일<br>
adm/shop_admin/orderformcartupdate.php<br>
adm/shop_admin/cookie.cancel.php<br>
shop/orderformupdate.php<br>
adm/shop_admin/orderform.php<br><br>

감사합니다.<br>

