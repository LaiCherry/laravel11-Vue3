<template>
    <v-layout class="h-100 content-height">
        <v-form action="javascript:void(0)" @submit="register" method="post" ref="RegisterForm" class="w-100">
            <div class="w-100 h-100 d-flex align-start pt-sm-8">
                <v-row justify="center" class="pa-0 ma-0 mt-4 mt-sm-0 mb-sm-16">
                    <v-col cols="12" md="8" class="text-center">
                        <h1 class="text-sm-h4 font-weight-bold">帳號註冊</h1>
                    </v-col>
                    <v-col cols="12" md="8">
                        <v-row>
                        <v-col cols="12">
                            <v-text-field
                            v-model="user.name"
                            variant="outlined"
                            class="rwd-text-field"
                            hide-details="auto"
                            :rules="[value => !!value || '此欄位必填']"
                            >
                            <template v-slot:label>
                                請輸入姓名
                            </template>
                            </v-text-field>
                        </v-col>
                        <v-col cols="12">
                            <v-text-field
                            v-model="user.email"
                            variant="outlined"
                            class="rwd-text-field"
                            hide-details="auto"
                            :rules="[value => !!value || '此欄位必填', val => /^[\w-\\.]+@([\w-]+\.)+[\w-]{2,4}$/.test(val) || 'email格式不符']"
                            >
                            <template v-slot:label>
                                請輸入Email帳號
                            </template>
                            </v-text-field>
                        </v-col>
                        <v-col cols="12">
                            <v-text-field
                            v-model="user.password"
                            :type="showPwd ? 'text' : 'password'"
                            variant="outlined"
                            hide-details="auto"
                            :rules="[value => !!value || '此欄位必填']"
                            class="rwd-text-field"
                            >
                                <template v-slot:append-inner>
                                <v-icon class="text-sm-h5" @click="showPwd = !showPwd">
                                    {{ showPwd ? 'mdi-eye' : 'mdi-eye-off' }}
                                </v-icon>
                                </template>
                            <template v-slot:label>
                                請輸入密碼
                            </template>
                            </v-text-field>
                        </v-col>
                        <v-col cols="12">
                            <v-text-field
                            v-model="user.passwordcheck"
                            :type="showPwdCheck ? 'text' : 'password'"
                            variant="outlined"
                            hide-details="auto"
                            :rules="[value => !!value || '此欄位必填', value => value===user.password || '密碼輸入不相符']"
                            class="rwd-text-field"
                            >
                                <template v-slot:append-inner>
                                <v-icon class="text-sm-h5" @click="showPwdCheck = !showPwdCheck">
                                    {{ showPwdCheck ? 'mdi-eye' : 'mdi-eye-off' }}
                                </v-icon>
                                </template>
                                <template v-slot:label>
                                請再次輸入密碼
                                </template>
                            </v-text-field>
                        </v-col>
                        <v-col cols="12">
                            <v-radio-group hide-details="auto" color="teal-darken-4" v-model="user.area_type" class="text-sm-h5" inline :rules="[value => !!value || '此欄位必選']">
                            <div class="d-flex align-center text-black text-h6 text-sm-h6"><strong>所屬區域</strong></div>&nbsp;
                            <v-radio value="1">
                                <template v-slot:label>
                                <div class="text-h6 text-sm-h6 px-sm-2"><strong class="text-green-darken-2 text-opacity">桃園廠</strong></div>
                                </template>
                            </v-radio>
                            <v-radio value="2">
                                <template v-slot:label>
                                <div class="text-h6 text-sm-h6 px-sm-2"><strong class="text-indigo-accent-4">大林廠</strong></div>
                                </template>
                            </v-radio>
                            <v-radio value="3">
                                <template v-slot:label>
                                <div class="text-h6 text-sm-h6 px-sm-2"><strong class="text-deep-purple-accent-4">大林廠與桃園廠</strong></div>
                                </template>
                            </v-radio>
                            </v-radio-group>
                        </v-col>
                        </v-row>
                    </v-col>
                    <v-col cols="12" md="8">
                        <div>
                            <vue-turnstile site-key="0x4AAAAAABBTNze_3DBbiJcX" v-model="turnstileToken" mode="non-interactive"/>
                            <div class="font-weight-bold my-2">ToturnstileTokenken: {{ turnstileToken }}</div>
                            <div class="font-weight-bold my-2">後端通過驗證與否: {{ TokenisVerified }}</div>
                        </div>
                        <div class="text-white text-decoration-none">
                        <!-- <button type="submit" :disabled="processing" class="btn btn-primary btn-block">
                            {{ processing ? "Please wait" : "Register" }}
                        </button> -->
                        <v-btn prepend-icon="mdi-account-plus" type="submit" :disabled="processing" class="text-white font-weight-black text-h6 text-sm-h5 smallup-btn-h smallup-btn-radius"
                            color="light-blue-darken-2" variant="flat" size="x-large" block>{{ processing ? "Please wait" : "註冊" }}
                        </v-btn>
                        </div>
                    </v-col>
                </v-row>
            </div>
        </v-form>
    </v-layout>
    <!-- <div class="container h-100">
        <div class="row h-100 align-items-center">
            <div class="col-12 col-md-6 offset-md-3">
                <div class="card shadow sm">
                    <div class="card-body">
                        <h1 class="text-center">Register</h1>
                        <hr/>
                        <form action="javascript:void(0)" @submit="register" class="row" method="post">
                            <div class="col-12" v-if="Object.keys(validationErrors).length > 0">
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        <li v-for="(value, key) in validationErrors" :key="key">{{ value[0] }}</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="form-group col-12">
                                <label for="name" class="font-weight-bold">Name</label>
                                <input type="text" name="name" v-model="user.name" id="name" placeholder="Enter name" class="form-control">
                            </div>
                            <div class="form-group col-12 my-2">
                                <label for="email" class="font-weight-bold">Email</label>
                                <input type="text" name="email" v-model="user.email" id="email" placeholder="Enter Email" class="form-control">
                            </div>
                            <div class="form-group col-12">
                                <label for="password" class="font-weight-bold">Password</label>
                                <input type="password" name="password" v-model="user.password" id="password" placeholder="Enter Password" class="form-control">
                            </div>
                            <div class="form-group col-12 my-2">
                                <label for="c_password" class="font-weight-bold">Confirm Password</label>
                                <input type="c_password" name="c_password" v-model="user.c_password" id="c_password" placeholder="Enter Password" class="form-control">
                            </div>
                            <div class="col-12 mb-2">
                                <button type="submit" :disabled="processing" class="btn btn-primary btn-block">
                                    {{ processing ? "Please wait" : "Register" }}
                                </button>
                            </div>
                            <div class="col-12 text-center">
                                <label>Already have an account? <router-link :to="{name:'login'}">Login Now!</router-link></label>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div> -->
</template>

<script>
import router from '@/router'
import VueTurnstile from 'vue-turnstile'
export default {
    components: { VueTurnstile },
    name:'register',
    data(){
        return {
            user:{
                name:"",
                email:"",
                password:"",
                passwordcheck:"",
                area_type:null,
                turnstileToken:"",
                TokenisVerified:false
            },
            showPwd:false,
            showPwdCheck:false,
            validationErrors:{},
            processing:false,
            turnstileToken: '',
            TokenisVerified: false
        }
    },
    watch: {
        async turnstileToken (val) {
            this.user.turnstileToken = val
            this.TokenisVerified = false
            if (val) {
               try {
                    const response = await axios.post('/api/verify-turnstile', {
                        token: val
                    });

                    if (response.data.success) {
                        this.TokenisVerified = true;
                    } else {
                        this.TokenisVerified = false;
                        alert('Verification failed');
                    }
                } catch (error) {
                    console.error('Error during Turnstile verification:', error);
                    alert('Error verifying Turnstile');
                } 
            }
        }
    },
    methods:{
        async register(){
            this.processing = true
            await axios.post('/api/register',this.user).then(response=>{
                localStorage.clear()
                this.validationErrors = {}
                alert(response.data.message)
            }).catch(({response})=>{
                if(response.status===422){
                    this.validationErrors = response.data.errors
                }else{
                    this.validationErrors = {}
                    alert(response.data.message)
                }
            }).finally(()=>{
                this.processing = false
            })
        },
    }
}
</script>