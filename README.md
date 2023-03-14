# oauth2.0
for thinkphp5.1

## 实力文档
/doc/*


## 密钥生成：
生成原始 RSA私钥文件
openssl genrsa -out rsa_private_key.pem 2048

生成RSA公钥文件
openssl rsa -in rsa_private_key.pem -pubout -out rsa_public_key.pem

将原始 RSA私钥转换为 pkcs8格式
一般不需要
openssl pkcs8 -topk8 -inform PEM -in rsa_private_key.pem -outform PEM -nocrypt -out private_key.pem