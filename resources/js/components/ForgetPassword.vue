<template>
    <v-layout class="h-100 content-height">
        <v-form action="javascript:void(0)" @submit="forgetPWD" method="post" ref="ForgetForm" class="w-100">
            <div class="w-100 h-100 d-flex align-start pt-sm-8">
                <v-row justify="center" class="pa-0 ma-0 mt-4 mt-sm-0 mb-sm-16">
                    <v-col cols="12" md="8" class="text-center">
                        <h1 class="text-sm-h4 font-weight-bold">忘記密碼</h1>
                    </v-col>
                    <v-col cols="12" md="6">
                        <v-row>
                        <v-col cols="12">
                            <v-text-field
                            v-model="forget.email"
                            ref="femail"
                            variant="outlined"
                            class="rwd-text-field"
                            hide-details="auto"
                            :rules="[value => !!value || '此欄位必填' , val => /^[\w-\\.]+@([\w-]+\.)+[\w-]{2,4}$/.test(val) || 'email格式不符']"
                            >
                            <template v-slot:label>
                                請輸入Email帳號
                            </template>
                            </v-text-field>
                        </v-col>
                        <v-col cols="12">
                            <v-btn
                                color="primary"
                                size="large"
                                :disabled="sendprocessing"
                                @click="sendVerifiCode"
                            >
                                {{ sendprocessing ? "驗證碼發送中" : "發送驗證碼" }}
                            </v-btn>
                        </v-col>
                        <v-col cols="12" md="12">
                        <br><div class="text-h6 text-sm-h6 px-sm-2"><strong>請至Email收信，取得驗證碼後，於下方進行驗證</strong></div>
                        </v-col>
                        <v-col cols="12">
                            <v-text-field
                                v-model="forget.vcode"
                                required
                                :rules="[value => !!value || '此欄位必填']"
                                :disabled="!ForgetForm"
                                variant="outlined"
                                hide-details="auto"
                            >
                                <template v-slot:label>
                                    請輸入驗證碼
                                </template>
                            </v-text-field>
                        </v-col>
                        <v-col cols="12">
                            <v-btn
                                color="primary"
                                :disabled="forgetprocessing"
                                size="large"
                                type="submit"
                            >
                                {{ forgetprocessing ? "驗證碼確認中" : "確認驗證碼" }}
                            </v-btn>
                        </v-col>
                        </v-row>
                    </v-col>
                    <!-- <v-col cols="12" md="8">
                         <p class="text-h6 text-sm-h6 px-sm-2"><strong>忘記密碼流程:</strong>
                         <div>輸入Email帳號 -> 至Email領取驗證碼 -> 於此頁面確認完成驗證碼 -> 至Email領取新密碼</div></p>
                    </v-col> -->
                </v-row>
            </div>
        </v-form>
    </v-layout>
</template>

<script>
import router from '@/router'
export default {
    name:'forgotpassword',
    data(){
        return {
            ForgetForm: false,
            forget:{
                email:"",
                vcode:""
            },
            sendprocessing: false,
            forgetprocessing: false
        }
    },
    methods:{
        async sendVerifiCode(){
            if(!this.$refs.femail.validate()) return
            this.sendprocessing = true
            await axios.post('/SendVcode',this.forget).then(response=>{
                console.log(response.data)
                this.ForgetForm = true
                alert(response.data.message)
            }).catch(({response})=>{
                if(response.status===422){
                    this.validationErrors = response.data.errors
                }else{
                    this.validationErrors = {}
                    alert(response.data.message)
                }
            }).finally(()=>{
                this.sendprocessing = false
            })
        },
        async forgetPWD(){
            if(!this.$refs.ForgetForm.validate()) return
            this.forgetprocessing = true
            await axios.post('/SendFpassword',this.forget).then(response=>{
                console.log(response.data)
                alert(response.data.message)
            }).catch(({response})=>{
                if(response.status===422){
                    this.validationErrors = response.data.errors
                }else{
                    this.validationErrors = {}
                    alert(response.data.message)
                }
            }).finally(()=>{
                this.forgetprocessing = false
            })
        }
    }
}
</script>