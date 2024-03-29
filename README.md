# 쿠키페이 플러그인 (쿠키페이 영카트 결제 연동 플러그인)

- 영카트 5.5.8.2.3 기반 제작 

- 결제 로그 기록  
    - DOCUMENT ROOT의 상위 디렉토리에 logs 디렉토리 생성  
    - logs 디렉토리에 707 권한 부여  

- 쿠키페이 플러그인 관련 테이블  
    - 자동으로 생성되며 "cookiepay_"와 같은 Prefix로 시작합니다.  

- https 설정 안내  
      - /common.php  
      - /cookiepay/cookiepay.constants.php  
      - 위 두 파일에서 아래 코드를 찾아 변경 (https 사용시: true, 미사용시: false)  
      ```  
      define('COOKIEPAY_USE_HTTPS', true);
      ```  
  
- 결제 통지 설정 안내 
    - 아래의 URL을 쿠키페이 담당자에게 전달해 [PG 통지 URL] 설정을 요청해 주세요.    
      ```  
      https://[플러그인설치도메인]/cookiepay/result.noti.php  
      ```  

▶ v1.1
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
        
    - 코드 추가  
        - /common.php  
        - /adm/admin.menu400.shop_1of2.php  
        - /adm/shop_admin/configform.php  
        - /adm/shop_admin/configformupdate.php  
        - /adm/shop_admin/orderform.php  
        - /adm/shop_admin/orderformcartupdate.php  
        - /adm/shop_admin/orderlist.php  
        - /shop/orderform.sub.php  
        - /shop/orderformupdate.php  
        - /shop/orderinquiryview.php  
        - /mobile/shop/orderform.sub.php  
        - /mobile/shop/orderformupdate.php  
        - /mobile/shop/orderinquiryview.php  
        - /theme/basic/shop/orderinquiryview.php  
        ```  
        ❗ 코드 추가는 영카트 원본파일이 수정된 경우이므로 아래와 같은 주석 구문을 검색해  
         해당 코드블록을 복사해 붙여넣어 주시기 바랍니다.

        // s: cookiepay-plugin -> "s:" 코드블록 시작을 의미
        // e: cookiepay-plugin -> "e:" 코드블록 종료를 의미

        (주의: 코드블록은 여러 개가 존재할 수 있습니다)
        ```  


▶ v1.2<br>
2024.01.09 이전에 영카트 플러그인을 설치한 경우에만 v1.2 부분을 적용하시기 바랍니다.<br>
영카트 플러그인을 새로 설치하는 경우에는 v1.1 부분만 적용하면 됩니다.<br>
(v1.2 버전을 따로 적용할 필요가 없습니다)<br>

- 영카트를 커스터마이징 하지 않은 경우  
    - 아래 디렉토리와 파일을 영카트 설치 디렉토리에 Copy & Paste(덮어쓰기) 
    - (cookiepay-plugin)  
        - /cookiepay  
        - /shop  

- 영카트를 커스터마이징한 경우  
    - Copy & Paste(붙여넣기)  
        - /cookiepay  
       
    - 코드 추가  
        - /shop/orderformupdate.php  
        ```  
        ❗ 코드 추가는 영카트 원본파일이 수정된 경우이므로 아래와 같은 주석 구문을 검색해 해당 코드블록을 복사해 붙여넣어 주시기 바랍니다.

        // s: cookiepay-plugin v1.2 -> "s:" 코드블록 시작을 의미
        // e: cookiepay-plugin v1.2 -> "e:" 코드블록 종료를 의미

        (주의: 코드블록은 여러 개가 존재할 수 있습니다)
        ```  
